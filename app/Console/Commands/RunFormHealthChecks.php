<?php

namespace App\Console\Commands;

use App\Jobs\CheckFormStructure;
use App\Jobs\CheckFormWebhook;
use App\Jobs\ReconcileFormResponses;
use App\Models\Form;
use Illuminate\Console\Command;

/**
 * Dispatches the scheduled per-form jobs (spec 5.9). Scheduled in routes/console.php.
 */
class RunFormHealthChecks extends Command
{
    protected $signature = 'forms:health {check : webhooks|structure|reconcile}';

    protected $description = 'Dispatch webhook / structure / reconcile checks for every active form';

    public function handle(): int
    {
        $check = (string) $this->argument('check');
        $count = 0;

        Form::query()->active()->each(function (Form $form) use ($check, &$count): void {
            match ($check) {
                'webhooks' => CheckFormWebhook::dispatch($form),
                'structure' => CheckFormStructure::dispatch($form),
                'reconcile' => ReconcileFormResponses::dispatch($form),
                default => throw new \InvalidArgumentException("Unknown check {$check}"),
            };
            $count++;
        });

        $this->info("Dispatched {$check} check for {$count} forms.");

        return self::SUCCESS;
    }
}
