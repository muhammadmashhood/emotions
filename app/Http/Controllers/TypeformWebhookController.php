<?php

namespace App\Http\Controllers;

use App\Domain\Typeform\WebhookSignature;
use App\Jobs\ProcessTypeformResponse;
use App\Models\Form;
use App\Models\WebhookDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /webhooks/typeform/{form_uuid}. Verify → store raw → 200 → queue. No heavy work here (spec 5.6).
 */
class TypeformWebhookController extends Controller
{
    public function __invoke(Request $request, string $formUuid): JsonResponse
    {
        $raw = (string) $request->getContent();
        $decoded = json_decode($raw, true);
        $meta = is_array($decoded) ? $decoded : [];

        $form = Form::withTrashed()->where('uuid', $formUuid)->first();

        if ($form === null) {
            $this->store(null, $raw, false, $meta);

            return response()->json(['ok' => false, 'error' => 'Unknown form.'], 404);
        }

        $valid = WebhookSignature::verify($raw, $form->webhook_secret, $request->header(WebhookSignature::HEADER));
        $delivery = $this->store($form, $raw, $valid, $meta);

        if (! $valid) {
            return response()->json(['ok' => false, 'error' => 'Invalid signature.'], 401);
        }

        ProcessTypeformResponse::dispatch($delivery->id);

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function store(?Form $form, string $raw, bool $valid, array $meta): WebhookDelivery
    {
        return WebhookDelivery::query()->create([
            'form_id' => $form?->id,
            'received_at' => now(),
            'signature_valid' => $valid,
            'event_id' => isset($meta['event_id']) ? mb_substr((string) $meta['event_id'], 0, 255) : null,
            'event_type' => isset($meta['event_type']) ? mb_substr((string) $meta['event_type'], 0, 255) : null,
            'response_token' => isset($meta['form_response']['token']) ? mb_substr((string) $meta['form_response']['token'], 0, 255) : null,
            'payload' => $raw,
        ]);
    }
}
