<?php

namespace Database\Seeders;

use App\Domain\Forms\FormVersioner;
use App\Domain\Submissions\SubmissionIngestor;
use App\Enums\Role;
use App\Enums\SubmissionSource;
use App\Enums\WebhookStatus;
use App\Models\Form;
use App\Models\Hospital;
use App\Models\TypeformConnection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\DemoResponseFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo data: super admin (SuperAdminSeeder), Ittefaq Hospital and CMH, 2 forms each built from the real fixture
 * structure, ~300 evaluations over 12 months with realistic variation, a few users per hospital.
 *
 * `php artisan db:seed --class=DemoSeeder` (or `migrate --seed`). Set DEMO_EVALUATIONS=50000 for a large dataset.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $total = max(4, (int) config('emotion.demo_evaluations', 300));
        $definition = $this->fixture();

        $connection = TypeformConnection::query()->firstOrCreate(
            ['label' => 'Demo Typeform account'],
            ['base_url' => 'https://api.typeform.com', 'access_token' => 'tfp_demo_token_not_real_0000000000', 'account_alias' => 'demo', 'last_verified_at' => now()],
        );

        $plan = [
            ['Ittefaq Hospital', 'ittefaq', 0.78, ['Ittefaq Hospital', 'Ittefaq Hospital Lahore', 'ittefaq hosp.'], ['OPD mystery shopping', 'Emergency mystery shopping']],
            ['CMH', 'cmh', 0.70, ['CMH Lahore', 'CMH', 'Combined Military Hospital'], ['OPD mystery shopping', 'Pharmacy mystery shopping']],
        ];

        $admin = User::query()->where('role', Role::SuperAdmin)->first();
        $versioner = app(FormVersioner::class);
        $ingestor = app(SubmissionIngestor::class);
        $perForm = (int) ceil($total / 4);
        $seed = 1;

        foreach ($plan as [$name, $slug, $quality, $facilities, $formTitles]) {
            $hospital = Hospital::query()->firstOrCreate(['slug' => $slug], ['name' => $name]);

            User::query()->firstOrCreate(['email' => "admin@{$slug}.example.com"], ['name' => "{$name} admin", 'password' => 'password', 'role' => Role::HospitalAdmin, 'hospital_id' => $hospital->id]);
            User::query()->firstOrCreate(['email' => "viewer@{$slug}.example.com"], ['name' => "{$name} viewer", 'password' => 'password', 'role' => Role::HospitalViewer, 'hospital_id' => $hospital->id]);
            User::query()->firstOrCreate(['email' => "viewer2@{$slug}.example.com"], ['name' => "{$name} second viewer", 'password' => 'password', 'role' => Role::HospitalViewer, 'hospital_id' => $hospital->id]);

            foreach ($formTitles as $i => $title) {
                $typeformId = 'demo'.strtoupper(substr($slug, 0, 3)).($i + 1);
                $form = Form::query()->where('typeform_form_id', $typeformId)->first();
                if ($form === null) {
                    $def = $definition;
                    $def['id'] = $typeformId;
                    $def['title'] = $title;

                    $form = Form::query()->create([
                        'hospital_id' => $hospital->id,
                        'typeform_connection_id' => $connection->id,
                        'typeform_form_id' => $typeformId,
                        'title' => $title,
                        'webhook_status' => WebhookStatus::Active,
                        'webhook_checked_at' => now(),
                        'linked_by' => $admin?->id,
                        'last_synced_at' => now(),
                    ]);
                    $version = $versioner->createInitialVersion($form, $def);
                    $version->forceFill(['needs_review' => false, 'reviewed_by' => $admin?->id, 'reviewed_at' => now()])->save();
                }

                if ($form->submissions()->exists()) {
                    continue;
                }

                $form->load('hospital', 'currentVersion');
                $factory = new DemoResponseFactory($form->currentVersion, $facilities, $quality - $i * 0.04, $seed++);
                $start = CarbonImmutable::now()->subMonths(12);
                $span = max(1, $start->diffInMinutes(CarbonImmutable::now()->subHours(2)));

                $this->command->getOutput()->write("Seeding {$perForm} evaluations for {$name} / {$title} … ");
                DB::transaction(function () use ($perForm, $factory, $start, $span, $ingestor, $form): void {
                    for ($n = 1; $n <= $perForm; $n++) {
                        // mild upward trend over the year
                        $at = $start->addMinutes((int) ($span * ($n - 0.5) / $perForm) + mt_rand(-600, 600))->setTime(mt_rand(8, 19), mt_rand(0, 59));
                        $ingestor->ingest($form, $factory->make($at, $n), SubmissionSource::Backfill);
                    }
                });
                $this->command->getOutput()->writeln('done');
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(): array
    {
        foreach ([base_path('docs/fixtures/form_definition_rACXmOJ2.json'), base_path('tests/Fixtures/typeform/form_definition_rACXmOJ2.json')] as $path) {
            if (file_exists($path)) {
                return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            }
        }

        throw new \RuntimeException('Form definition fixture not found.');
    }
}
