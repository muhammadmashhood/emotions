<?php

namespace App\Console\Commands;

use App\Models\Form;
use App\Models\Hospital;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Removes the demo hospitals/forms/evaluations created by the demo seeder (Typeform ids starting with "demo").
 * Useful when a local database with demo data was imported into production.
 */
class PurgeDemoData extends Command
{
    protected $signature = 'app:purge-demo-data {--force : Run without the confirmation prompt}';

    protected $description = 'Delete demo hospitals, forms, users and evaluations seeded by DemoSeeder';

    public function handle(): int
    {
        $forms = Form::withTrashed()->where('typeform_form_id', 'like', 'demo%')->get();
        $hospitalIds = $forms->pluck('hospital_id')->unique();
        $hospitals = Hospital::query()->whereIn('id', $hospitalIds)
            ->orWhereIn('slug', ['ittefaq', 'cmh'])
            ->get()
            // only hospitals whose every form is a demo form are removed entirely
            ->filter(fn (Hospital $h) => $h->forms()->withTrashed()->where('typeform_form_id', 'not like', 'demo%')->doesntExist());

        if ($forms->isEmpty() && $hospitals->isEmpty()) {
            $this->info('No demo data found.');

            return self::SUCCESS;
        }

        $this->table(['Demo form', 'Typeform id', 'Hospital', 'Evaluations'], $forms->map(fn (Form $f) => [$f->title, $f->typeform_form_id, $f->hospital_id, $f->submissions()->count()])->all());
        $this->line('Hospitals to delete entirely (users, forms, evaluations): '.($hospitals->pluck('name')->implode(', ') ?: 'none'));

        if (! $this->option('force') && ! $this->confirm('Delete all of the above?')) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($forms, $hospitals): void {
            foreach ($forms as $form) {
                $form->submissions()->delete();     // answers and section scores cascade
                $form->deliveries()->delete();
                $form->versions()->delete();        // sections and fields cascade
                $form->forceDelete();
            }
            foreach ($hospitals as $hospital) {
                $hospital->users()->delete();
                $hospital->delete();                // branches cascade
            }
        });

        $this->info('Demo data removed: '.$forms->count().' forms, '.$hospitals->count().' hospitals.');

        return self::SUCCESS;
    }
}
