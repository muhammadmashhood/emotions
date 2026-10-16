<?php

namespace Database\Seeders;

use App\Domain\Forms\FormVersioner;
use App\Domain\Scoring\ScoringEngine;
use App\Domain\Scoring\ScoringMapping;
use App\Domain\Submissions\SubmissionIngestor;
use App\Domain\Typeform\AnswerNormalizer;
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
use Illuminate\Support\Str;

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
                if ($perForm > 2000) {
                    $this->bulkSeed($form, $factory, $start, $span, $perForm);
                } else {
                    DB::transaction(function () use ($perForm, $factory, $start, $span, $ingestor, $form): void {
                        for ($n = 1; $n <= $perForm; $n++) {
                            // mild upward trend over the year
                            $at = $start->addMinutes((int) ($span * ($n - 0.5) / $perForm) + mt_rand(-600, 600))->setTime(mt_rand(8, 19), mt_rand(0, 59));
                            $ingestor->ingest($form, $factory->make($at, $n), SubmissionSource::Backfill);
                        }
                    });
                }
                $this->command->getOutput()->writeln('done');
            }
        }
    }

    /**
     * Fast path for large datasets (`--large`): runs the pure scoring engine and bulk-inserts rows,
     * bypassing per-row Eloquent. Produces the same data shape as the ingestor.
     */
    private function bulkSeed(Form $form, DemoResponseFactory $factory, CarbonImmutable $start, int $span, int $count): void
    {
        $version = $form->currentVersion;
        $mapping = ScoringMapping::fromVersion($version);
        $settings = $form->settings;
        $engine = new ScoringEngine;
        $normalizer = new AnswerNormalizer;
        $infoKeys = collect($mapping->fields)->filter(fn ($f) => $f->infoKey !== null)->mapWithKeys(fn ($f) => [$f->typeformFieldId => $f->infoKey]);
        $now = now();

        for ($offset = 0; $offset < $count; $offset += 500) {
            $batch = min(500, $count - $offset);
            $submissions = [];
            $payloads = [];

            for ($i = 0; $i < $batch; $i++) {
                $n = $offset + $i + 1;
                $at = $start->addMinutes((int) ($span * ($n - 0.5) / $count) + mt_rand(-600, 600))->setTime(mt_rand(8, 19), mt_rand(0, 59));
                $payload = $factory->make($at, $n);
                $answers = $normalizer->normalizeAll($payload['answers']);
                $result = $engine->score($mapping, $answers, $settings);

                $info = [];
                foreach ($answers as $a) {
                    if ($infoKeys->has($a->fieldId) && $a->valueText !== null) {
                        $info[$infoKeys[$a->fieldId]] = $a->valueText;
                    }
                }
                $info['branch'] = $payload['hidden']['branch'];

                $submissions[] = [
                    'uuid' => (string) Str::uuid(),
                    'form_id' => $form->id,
                    'form_version_id' => $version->id,
                    'hospital_id' => $form->hospital_id,
                    'response_token' => $payload['token'],
                    'submitted_at' => $at->toDateTimeString(),
                    'landed_at' => $at->subMinutes(12)->toDateTimeString(),
                    'is_partial' => false,
                    'status' => 'approved',
                    'source' => 'backfill',
                    'raw' => json_encode(['answers' => $payload['answers'], 'hidden' => $payload['hidden'], 'variables' => [], 'calculated' => $payload['calculated'], 'event_type' => null]),
                    'info' => json_encode($info),
                    'points' => $result->points,
                    'max_points' => $result->maxPoints,
                    'overall_pct' => $result->overallPct,
                    'rag' => $result->rag?->value,
                    'scored_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $payloads[] = [$answers, $result];
            }

            DB::table('submissions')->insert($submissions);
            $ids = DB::table('submissions')->where('form_id', $form->id)->whereIn('response_token', array_column($submissions, 'response_token'))->pluck('id', 'response_token');

            $answerRows = [];
            $sectionRows = [];
            foreach ($submissions as $i => $row) {
                $submissionId = $ids[$row['response_token']];
                [$answers, $result] = $payloads[$i];
                foreach ($answers as $a) {
                    $field = $mapping->field($a->fieldId);
                    $answerRows[] = [
                        'submission_id' => $submissionId,
                        'typeform_field_id' => $a->fieldId,
                        'form_field_id' => $field?->id,
                        'answer_type' => $a->answerType,
                        'value_text' => $a->valueText,
                        'value_number' => $a->valueNumber,
                        'value_json' => $a->valueJson === null ? null : json_encode($a->valueJson),
                        'points' => $result->pointsFor($a->fieldId),
                        'max_points' => $result->maxPointsFor($a->fieldId),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                foreach ($result->sections as $section) {
                    $sectionRows[] = [
                        'submission_id' => $submissionId,
                        'section_id' => $section->sectionId,
                        'section_name' => $section->name,
                        'display_order' => $section->displayOrder,
                        'points' => $section->points,
                        'max_points' => $section->maxPoints,
                        'pct' => $section->pct,
                        'weight' => $section->weight,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            foreach (array_chunk($answerRows, 2000) as $chunk) {
                DB::table('answers')->insert($chunk);
            }
            foreach (array_chunk($sectionRows, 2000) as $chunk) {
                DB::table('submission_section_scores')->insert($chunk);
            }
            $this->command->getOutput()->write('.');
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
