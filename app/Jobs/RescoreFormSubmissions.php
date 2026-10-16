<?php

namespace App\Jobs;

use App\Domain\Scoring\Rescorer;
use App\Models\Form;
use App\Models\FormVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Re-score a form's submissions (spec 7.4). Body implemented with the scoring engine in M4.
 */
class RescoreFormSubmissions implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 3600;

    public function __construct(
        public Form $form,
        public ?FormVersion $version = null,
        public bool $useLatestMapping = false,
    ) {}

    public function handle(): void
    {
        if (class_exists(Rescorer::class)) {
            app(Rescorer::class)->rescoreForm($this->form, $this->version, $this->useLatestMapping);
        }
    }
}
