<?php

namespace App\Jobs;

use App\Domain\Forms\FormVersioner;
use App\Models\Form;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckFormStructure implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public Form $form) {}

    public function handle(FormVersioner $versioner): void
    {
        if (! $this->form->isActive()) {
            return;
        }
        $versioner->refreshFromTypeform($this->form);
    }
}
