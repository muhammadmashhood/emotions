<?php

namespace App\Jobs;

use App\Domain\Forms\WebhookRegistrar;
use App\Models\Form;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckFormWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public Form $form) {}

    public function handle(WebhookRegistrar $registrar): void
    {
        if (! $this->form->isActive()) {
            return;
        }
        $registrar->check($this->form);
    }
}
