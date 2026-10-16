<?php

use App\Domain\Forms\FormVersioner;
use App\Domain\Typeform\WebhookSignature;
use App\Enums\FormStatus;
use App\Jobs\ProcessTypeformResponse;
use App\Models\Form;
use App\Models\Submission;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->form = Form::factory()->create(['typeform_form_id' => 'rACXmOJ2']);
    app(FormVersioner::class)->createInitialVersion($this->form, fixture('form_definition_rACXmOJ2.json'));
    $this->raw = (string) file_get_contents(__DIR__.'/../../Fixtures/typeform/webhook_payload_rACXmOJ2.json');
});

function postWebhook($test, Form $form, string $raw, ?string $signature): TestResponse
{
    $headers = ['CONTENT_TYPE' => 'application/json'];
    if ($signature !== null) {
        $headers['HTTP_TYPEFORM_SIGNATURE'] = $signature;
    }

    return $test->call('POST', '/webhooks/typeform/'.$form->uuid, [], [], [], $headers, $raw);
}

test('valid signature → 200, delivery stored, job dispatched on the webhooks queue', function () {
    Queue::fake();

    $response = postWebhook($this, $this->form, $this->raw, WebhookSignature::sign($this->raw, $this->form->webhook_secret));

    $response->assertOk()->assertJson(['ok' => true]);

    $delivery = WebhookDelivery::query()->firstOrFail();
    expect($delivery->form_id)->toBe($this->form->id)
        ->and($delivery->signature_valid)->toBeTrue()
        ->and($delivery->event_id)->toBe('01JXYZ6WJ2P5K3Q8R9S0T1U2V3')
        ->and($delivery->event_type)->toBe('form_response')
        ->and($delivery->response_token)->toBe('r8f3a1c2d9e0b7f6a5d4c3b2a1')
        ->and($delivery->payload)->toBe($this->raw)
        ->and($delivery->processed_at)->toBeNull();

    Queue::assertPushedOn('webhooks', ProcessTypeformResponse::class, fn (ProcessTypeformResponse $job) => $job->deliveryId === $delivery->id);
});

test('the webhook route needs no CSRF token or session', function () {
    Queue::fake();
    $this->withMiddleware();

    postWebhook($this, $this->form, $this->raw, WebhookSignature::sign($this->raw, $this->form->webhook_secret))
        ->assertOk()
        ->assertCookieMissing(config('session.cookie'));
});

test('invalid signature → 401, delivery stored as invalid, nothing ingested', function () {
    Queue::fake();

    postWebhook($this, $this->form, $this->raw, WebhookSignature::sign($this->raw, 'wrong-secret'))->assertStatus(401);
    postWebhook($this, $this->form, $this->raw, null)->assertStatus(401);
    postWebhook($this, $this->form, $this->raw.' ', WebhookSignature::sign($this->raw, $this->form->webhook_secret))->assertStatus(401);

    expect(WebhookDelivery::query()->where('signature_valid', false)->count())->toBe(3)
        ->and(Submission::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('unknown uuid → 404 and an orphan delivery row', function () {
    Queue::fake();

    $this->call('POST', '/webhooks/typeform/'.Str::uuid(), [], [], [], ['CONTENT_TYPE' => 'application/json'], $this->raw)
        ->assertNotFound();

    $delivery = WebhookDelivery::query()->firstOrFail();
    expect($delivery->form_id)->toBeNull()->and($delivery->signature_valid)->toBeFalse();
    Queue::assertNothingPushed();
});

test('unlinked forms still accept and store deliveries', function () {
    Queue::fake();
    $this->form->update(['status' => FormStatus::Unlinked]);

    postWebhook($this, $this->form, $this->raw, WebhookSignature::sign($this->raw, $this->form->webhook_secret))->assertOk();
    Queue::assertPushed(ProcessTypeformResponse::class);
});

test('the endpoint is rate limited per IP', function () {
    Queue::fake();
    $sig = WebhookSignature::sign($this->raw, $this->form->webhook_secret);
    for ($i = 0; $i < 120; $i++) {
        postWebhook($this, $this->form, $this->raw, $sig)->assertOk();
    }
    postWebhook($this, $this->form, $this->raw, $sig)->assertStatus(429);
});
