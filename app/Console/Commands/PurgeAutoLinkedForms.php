<?php

namespace App\Console\Commands;

use App\Domain\Forms\FormLinker;
use App\Models\AuditLog;
use App\Models\Form;
use Illuminate\Console\Command;

/**
 * Removes every form the hourly workspace auto-link job linked (audit row "form.linked" with automatic = true),
 * together with its webhook, versions and imported evaluations. Forms linked by a person are never touched.
 */
class PurgeAutoLinkedForms extends Command
{
    protected $signature = 'forms:purge-auto-linked {--force : Actually delete; without it the command only lists what it would remove}';

    protected $description = 'Delete forms that were linked automatically from a mapped Typeform workspace (dry run unless --force)';

    public function handle(FormLinker $linker): int
    {
        $formIds = AuditLog::query()
            ->where('action', 'form.linked')
            ->where('subject_type', Form::class)
            ->where('changes->automatic', true)
            ->pluck('subject_id')
            ->unique();

        $forms = Form::withTrashed()->with('hospital')->withCount('submissions')->whereIn('id', $formIds)->orderBy('hospital_id')->orderBy('title')->get();

        if ($forms->isEmpty()) {
            $this->info('No automatically linked forms found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Hospital', 'Form', 'Typeform id', 'Status', 'Evaluations'],
            $forms->map(fn (Form $f) => [$f->hospital->name ?? '-', $f->title, $f->typeform_form_id, $f->status->value, $f->submissions_count])->all(),
        );

        if (! $this->option('force')) {
            $this->warn('Dry run: nothing deleted. Re-run with --force to remove the '.$forms->count().' form(s) above, their webhooks and evaluations.');

            return self::SUCCESS;
        }

        $removed = 0;
        foreach ($forms as $form) {
            try {
                $linker->delete($form, null, automatic: true);
                $removed++;
            } catch (\Throwable $e) {
                $this->error("Could not remove {$form->title} ({$form->typeform_form_id}): {$e->getMessage()}");
            }
        }

        $this->info("Removed {$removed} form(s).");

        return $removed === $forms->count() ? self::SUCCESS : self::FAILURE;
    }
}
