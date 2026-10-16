<?php

namespace App\Jobs;

use App\Domain\Submissions\SubmissionIngestor;
use App\Enums\SubmissionSource;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Processes one stored webhook delivery: idempotency → version check → normalise → score → save (spec 5.7).
 */
class ProcessTypeformResponse implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public int $deliveryId)
    {
        $this->onQueue('webhooks');
    }

    public function handle(SubmissionIngestor $ingestor): void
    {
        $delivery = WebhookDelivery::query()->with('form.hospital', 'form.currentVersion')->find($this->deliveryId);

        if ($delivery === null || $delivery->processed_at !== null || ! $delivery->signature_valid) {
            return;
        }

        $form = $delivery->form;
        if ($form === null) {
            $delivery->forceFill(['error' => 'Form not found.'])->save();

            return;
        }

        $payload = $delivery->decodedPayload();
        $response = is_array($payload['form_response'] ?? null) ? $payload['form_response'] : null;
        if ($response === null) {
            $delivery->forceFill(['processed_at' => now(), 'error' => 'Payload has no form_response.'])->save();

            return;
        }

        try {
            $outcome = $ingestor->ingest($form, $response, SubmissionSource::Webhook, $payload['event_type'] ?? $delivery->event_type);
            $delivery->forceFill([
                'processed_at' => now(),
                'error' => $outcome->isDuplicate() ? 'Duplicate (already stored).' : null,
            ])->save();
        } catch (Throwable $e) {
            $delivery->forceFill(['error' => mb_substr($e->getMessage(), 0, 2000)])->save();
            throw $e;
        }
    }
}
