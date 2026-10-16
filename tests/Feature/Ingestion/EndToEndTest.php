<?php

use App\Domain\Forms\FormLinker;
use App\Domain\Typeform\WebhookSignature;
use App\Enums\Rag;
use App\Enums\WebhookStatus;
use App\Models\Hospital;
use App\Models\Submission;
use App\Models\TypeformConnection;
use Illuminate\Support\Facades\Http;

test('end to end: link a form → Typeform posts a webhook → scored evaluation on the right hospital', function () {
    $connection = TypeformConnection::factory()->create();
    $ittefaq = Hospital::factory()->create(['name' => 'Ittefaq Hospital']);
    $cmh = Hospital::factory()->create(['name' => 'CMH']);
    $admin = superAdmin();

    Http::fake([
        '*/forms/rACXmOJ2' => Http::response(fixture('form_definition_rACXmOJ2.json')),
        '*/forms/rACXmOJ2/webhooks/*' => Http::response(['id' => 'wh', 'enabled' => true]),
        '*/forms/rACXmOJ2/responses*' => Http::response(['total_items' => 0, 'page_count' => 0, 'items' => []]),
    ]);

    // 1. Link (queue is sync → the backfill runs immediately and finds nothing)
    $form = app(FormLinker::class)->link($ittefaq, $connection, 'rACXmOJ2', $admin);
    expect($form->webhook_status)->toBe(WebhookStatus::Active)
        ->and($form->fresh()->last_synced_at)->not->toBeNull()
        ->and(Submission::query()->count())->toBe(0);

    // 2. Typeform calls the webhook URL that we registered, signed with the per-form secret
    $raw = (string) file_get_contents(__DIR__.'/../../Fixtures/typeform/webhook_payload_rACXmOJ2.json');
    $url = parse_url($form->webhookUrl(), PHP_URL_PATH);
    $this->call('POST', $url, [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_TYPEFORM_SIGNATURE' => WebhookSignature::sign($raw, $form->webhook_secret),
    ], $raw)->assertOk()->assertJson(['ok' => true]);

    // 3. The evaluation is scored and belongs to Ittefaq, not CMH
    $submission = Submission::query()->firstOrFail();
    expect($submission->hospital_id)->toBe($ittefaq->id)
        ->and($submission->overall_pct)->toBe(77.65)
        ->and($submission->rag)->toBe(Rag::Amber)
        ->and($submission->sectionScores()->count())->toBe(10)
        ->and(Submission::query()->forHospital($cmh)->count())->toBe(0)
        ->and(Submission::query()->forHospital($ittefaq)->count())->toBe(1);
});
