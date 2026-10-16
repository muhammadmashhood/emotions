<?php

namespace App\Jobs;

use App\Domain\Submissions\ResponseSyncer;
use App\Models\Form;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Page through all completed responses of a form and ingest them (spec 5.8). Body implemented with the syncer in M4.
 */
class BackfillFormResponses implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 1800;

    public function __construct(public Form $form) {}

    public function uniqueId(): string
    {
        return (string) $this->form->id;
    }

    public function handle(): void
    {
        if (class_exists(ResponseSyncer::class)) {
            app(ResponseSyncer::class)->backfill($this->form);
        }
    }
}
