<?php

namespace App\Console\Commands;

use App\Domain\Scoring\Rescorer;
use App\Models\Form;
use Illuminate\Console\Command;

class RescoreEvaluations extends Command
{
    protected $signature = 'evaluations:rescore {form_uuid : The form uuid} {--latest : Score every evaluation against the current mapping} {--on-version= : Only evaluations on this version number}';

    protected $description = 'Recalculate scores for all evaluations of a form';

    public function handle(Rescorer $rescorer): int
    {
        $form = Form::withTrashed()->where('uuid', $this->argument('form_uuid'))->first();
        if ($form === null) {
            $this->error('Form not found.');

            return self::FAILURE;
        }

        $version = null;
        if ($this->option('on-version') !== null) {
            $version = $form->versions()->where('version_number', (int) $this->option('on-version'))->first();
            if ($version === null) {
                $this->error('Version not found.');

                return self::FAILURE;
            }
        }

        $count = $rescorer->rescoreForm($form, $version, (bool) $this->option('latest'));
        $this->info("Re-scored {$count} evaluations of {$form->title}.");

        return self::SUCCESS;
    }
}
