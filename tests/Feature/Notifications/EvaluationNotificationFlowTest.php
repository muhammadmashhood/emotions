<?php

use App\Domain\Forms\FormVersioner;
use App\Domain\Submissions\SubmissionIngestor;
use App\Domain\Typeform\WebhookSignature;
use App\Enums\SubmissionSource;
use App\Mail\EvaluationPublishedMail;
use App\Mail\EvaluationSubmittedMail;
use App\Models\Form;
use App\Models\Hospital;
use App\Models\Submission;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Mail;

/**
 * When the emails fire: live webhook arrivals and Approve actions. Never backfills, partials or duplicates.
 */
beforeEach(function () {
    Mail::fake();
    $this->admin = superAdmin();
    $this->hospital = Hospital::factory()->create(['require_review' => false]);
    $this->hospitalAdmin = hospitalAdmin($this->hospital);
    $this->form = Form::factory()->for($this->hospital)->create(['typeform_form_id' => 'rACXmOJ2', 'linked_by' => $this->admin->id]);
    app(FormVersioner::class)->createInitialVersion($this->form, fixture('form_definition_rACXmOJ2.json'));
    $this->raw = (string) file_get_contents(__DIR__.'/../../Fixtures/typeform/webhook_payload_rACXmOJ2.json');
    $this->partialRaw = (string) file_get_contents(__DIR__.'/../../Fixtures/typeform/webhook_payload_partial.json');
});

function deliverWebhook($test, Form $form, string $raw): WebhookDelivery
{
    $test->call('POST', '/webhooks/typeform/'.$form->uuid, [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_TYPEFORM_SIGNATURE' => WebhookSignature::sign($raw, $form->webhook_secret),
    ], $raw)->assertOk();

    return WebhookDelivery::query()->latest('id')->firstOrFail();
}

test('a live complete response emails staff, and hospital admins when the hospital has no review', function () {
    deliverWebhook($this, $this->form, $this->raw);

    Mail::assertQueued(EvaluationSubmittedMail::class, 1);
    Mail::assertQueued(EvaluationSubmittedMail::class, fn ($m) => $m->hasTo($this->admin->email));
    Mail::assertQueued(EvaluationPublishedMail::class, 1);
    Mail::assertQueued(EvaluationPublishedMail::class, fn ($m) => $m->hasTo($this->hospitalAdmin->email));
});

test('with review required only staff are emailed on arrival', function () {
    $this->hospital->update(['require_review' => true]);

    deliverWebhook($this, $this->form, $this->raw);

    Mail::assertQueued(EvaluationSubmittedMail::class, 1);
    Mail::assertNotQueued(EvaluationPublishedMail::class);
});

test('duplicates do not email again', function () {
    deliverWebhook($this, $this->form, $this->raw);
    deliverWebhook($this, $this->form, $this->raw);

    Mail::assertQueued(EvaluationSubmittedMail::class, 1);
    Mail::assertQueued(EvaluationPublishedMail::class, 1);
});

test('a partial response does not email until it completes, then exactly once', function () {
    deliverWebhook($this, $this->form, $this->partialRaw);
    Mail::assertNothingQueued();

    deliverWebhook($this, $this->form, $this->raw);
    Mail::assertQueued(EvaluationSubmittedMail::class, 1);
    Mail::assertQueued(EvaluationPublishedMail::class, 1);

    deliverWebhook($this, $this->form, $this->partialRaw);
    Mail::assertQueued(EvaluationSubmittedMail::class, 1);
});

test('backfilled and reconciled responses never email', function () {
    $payload = json_decode($this->raw, true);
    app(SubmissionIngestor::class)->ingest($this->form, $payload['form_response'], SubmissionSource::Backfill);
    $payload['form_response']['token'] = 'another-token';
    app(SubmissionIngestor::class)->ingest($this->form, $payload['form_response'], SubmissionSource::Reconcile);

    expect(Submission::query()->count())->toBe(2);
    Mail::assertNothingQueued();
});
