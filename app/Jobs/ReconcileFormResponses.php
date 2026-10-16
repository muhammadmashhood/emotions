<?php

namespace App\Jobs;

use App\Domain\Submissions\ResponseSyncer;
use App\Models\Form;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReconcileFormResponses implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 900;

    public function __construct(public Form $form) {}

    public function uniqueId(): string
    {
        return (string) $this->form->id;
    }

    public function handle(ResponseSyncer $syncer): void
    {
        if (! $this->form->isActive()) {
            return;
        }
        $syncer->reconcile($this->form);
    }
}
