<?php

use App\Domain\Forms\FormVersioner;
use App\Domain\Submissions\SubmissionIngestor;
use App\Domain\Typeform\WebhookSignature;
use App\Enums\Rag;
use App\Enums\SubmissionSource;
use App\Enums\SubmissionStatus;
use App\Models\Form;
use App\Models\Hospital;
use App\Models\Submission;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->form = Form::factory()->create(['typeform_form_id' => 'rACXmOJ2']);
    $this->v1 = app(FormVersioner::class)->createInitialVersion($this->form, fixture('form_definition_rACXmOJ2.json'));
    $this->raw = (string) file_get_contents(__DIR__.'/../../Fixtures/typeform/webhook_payload_rACXmOJ2.json');
});

function deliver($test, Form $form, string $raw): WebhookDelivery
{
    $test->call('POST', '/webhooks/typeform/'.$form->uuid, [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_TYPEFORM_SIGNATURE' => WebhookSignature::sign($raw, $form->webhook_secret),
    ], $raw)->assertOk();

    return WebhookDelivery::query()->latest('id')->firstOrFail();
}

test('posting the real webhook fixture creates one scored evaluation with the expected section percentages', function () {
    $delivery = deliver($this, $this->form, $this->raw); // queue is sync in tests → processed inline

    expect($delivery->fresh()->processed_at)->not->toBeNull()->and($delivery->fresh()->error)->toBeNull();

    $submission = Submission::query()->firstOrFail();
    expect($submission->form_id)->toBe($this->form->id)
        ->and($submission->hospital_id)->toBe($this->form->hospital_id)
        ->and($submission->form_version_id)->toBe($this->v1->id)
        ->and($submission->response_token)->toBe('r8f3a1c2d9e0b7f6a5d4c3b2a1')
        ->and($submission->is_partial)->toBeFalse()
        ->and($submission->status)->toBe(SubmissionStatus::Approved)
        ->and($submission->source)->toBe(SubmissionSource::Webhook)
        ->and($submission->submitted_at->toIso8601ZuluString())->toBe('2026-03-14T10:19:47Z')
        ->and($submission->info)->toEqualCanonicalizing([
            'facility' => 'Ittefaq Hospital',
            'location' => 'Lahore',
            'visit_date' => '2026-03-14',
            'booking_method' => 'Phone call',
            'doctor' => 'Dr. Farhan Siddiqui',
            'branch' => 'Main campus',
            'recommend' => '9',
        ])
        ->and($submission->answers()->count())->toBe(45)
        ->and($submission->raw['calculated'])->toBe(['score' => 2]);

    $sections = $submission->sectionScores()->get()->keyBy('section_name');
    expect($sections->map(fn ($s) => $s->pct)->all())->toBe([
        'General Information' => null,
        'Website/ App booking' => null,
        'Phone booking' => 85.0,
        'Arrival & Reception' => 75.0,
        'Waiting Area' => 60.0,
        'Nursing Staff' => 92.0,
        'Doctor Consultation' => 80.0,
        'Pharmacy & Billing' => 77.5,
        'Facility & Cleanliness' => 60.0,
        'Overall' => 91.67,
    ]);
    expect($sections['Arrival & Reception']->points)->toBe(30.0)->and($sections['Arrival & Reception']->max_points)->toBe(40.0);

    expect($submission->overall_pct)->toBe(77.65)  // mean of the 8 answered sections
        ->and($submission->rag)->toBe(Rag::Amber)
        ->and($submission->points)->toBe(259.0)
        ->and($submission->max_points)->toBe(334.0)
        ->and($submission->scored_at)->not->toBeNull();

    // Typeform's own score (2) is ignored; the NA dropdown and the text answers are not scored.
    $na = $submission->answers()->where('value_text', 'N/A')->firstOrFail();
    expect($na->points)->toBeNull()->and($na->max_points)->toBeNull();
    $file = $submission->answers()->where('answer_type', 'file_url')->firstOrFail();
    expect($file->value_text)->toBe('receipt.jpg')->and($file->value_json['url'])->toContain('api.typeform.com');
});

test('duplicate token → one submission, delivery marked duplicate', function () {
    deliver($this, $this->form, $this->raw);
    $second = deliver($this, $this->form, $this->raw);

    expect(Submission::query()->count())->toBe(1)
        ->and($second->fresh()->processed_at)->not->toBeNull()
        ->and($second->fresh()->error)->toStartWith('Duplicate');
});

test('partial then complete → one submission, the complete response wins', function () {
    $partialRaw = (string) file_get_contents(__DIR__.'/../../Fixtures/typeform/webhook_payload_partial.json');

    deliver($this, $this->form, $partialRaw);
    $partial = Submission::query()->firstOrFail();
    expect($partial->is_partial)->toBeTrue()
        ->and($partial->submitted_at)->toBeNull()
        ->and($partial->overall_pct)->toBeNull()
        ->and($partial->answers()->count())->toBe(5);

    deliver($this, $this->form, $this->raw);
    expect(Submission::query()->count())->toBe(1);
    $complete = Submission::query()->firstOrFail();
    expect($complete->id)->toBe($partial->id)
        ->and($complete->is_partial)->toBeFalse()
        ->and($complete->overall_pct)->toBe(77.65)
        ->and($complete->answers()->count())->toBe(45);

    // a partial arriving after the complete one is ignored
    deliver($this, $this->form, $partialRaw);
    expect(Submission::query()->firstOrFail()->is_partial)->toBeFalse();
});

test('unknown field id triggers a structure refresh (faked API) and the response is scored on the new version', function () {
    Http::fake(['*/forms/rACXmOJ2' => Http::response(fixture('form_definition_rACXmOJ2_v2.json'))]);
    $raw = (string) file_get_contents(__DIR__.'/../../Fixtures/typeform/webhook_payload_unknown_field.json');

    deliver($this, $this->form, $raw);

    $this->form->refresh();
    $v2 = $this->form->currentVersion;
    expect($v2->version_number)->toBe(2)->and($v2->needs_review)->toBeTrue();

    $submission = Submission::query()->firstOrFail();
    expect($submission->form_version_id)->toBe($v2->id);
    $newAnswer = $submission->answers()->where('typeform_field_id', 'NEWFIELD01')->firstOrFail();
    expect($newAnswer->form_field_id)->not->toBeNull()->and($newAnswer->points)->toBe(7.0)->and($newAnswer->max_points)->toBe(10.0);

    // Section 10 now includes the new question: (3 + 9 + 10 + 7) / (4 + 10 + 10 + 10) = 85.29
    expect((float) $submission->sectionScores()->where('section_name', 'Overall')->value('pct'))->toBe(85.29);
    Http::assertSentCount(1);
});

test('structure refresh is throttled to once per 5 minutes per form', function () {
    Cache::put("form:{$this->form->id}:structure-refresh", true, 300);
    $raw = (string) file_get_contents(__DIR__.'/../../Fixtures/typeform/webhook_payload_unknown_field.json');

    deliver($this, $this->form, $raw);

    Http::assertNothingSent();
    $submission = Submission::query()->firstOrFail();
    expect($submission->form_version_id)->toBe($this->v1->id)
        ->and($submission->answers()->where('typeform_field_id', 'NEWFIELD01')->value('form_field_id'))->toBeNull();
});

test('a failing structure refresh does not block ingestion', function () {
    Http::fake(['*/forms/rACXmOJ2' => Http::response('down', 500)]);
    $raw = (string) file_get_contents(__DIR__.'/../../Fixtures/typeform/webhook_payload_unknown_field.json');

    deliver($this, $this->form, $raw);

    expect(Submission::query()->count())->toBe(1);
});

test('hospitals with require_review get pending evaluations', function () {
    $this->form->hospital->update(['require_review' => true]);

    deliver($this, $this->form, $this->raw);

    expect(Submission::query()->firstOrFail()->status)->toBe(SubmissionStatus::PendingReview);
});

test('ingestor handles Responses API items (no event type, partial when submitted_at missing)', function () {
    $page = fixture('responses_page.json');
    $ingestor = app(SubmissionIngestor::class);

    $outcome = $ingestor->ingest($this->form, $page['items'][0], SubmissionSource::Backfill);
    expect($outcome->status)->toBe('created')
        ->and($outcome->submission->source)->toBe(SubmissionSource::Backfill)
        ->and($outcome->submission->overall_pct)->toBe(77.65);

    $item = $page['items'][1];
    unset($item['submitted_at']);
    $partial = $ingestor->ingest($this->form, $item, SubmissionSource::Backfill);
    expect($partial->submission->is_partial)->toBeTrue();

    expect($ingestor->ingest($this->form, $page['items'][0], SubmissionSource::Reconcile)->isDuplicate())->toBeTrue();
});

test('processing errors are recorded on the delivery and the job fails visibly', function () {
    // Remove the current version so ingestion throws.
    $this->form->forceFill(['current_version_id' => null])->save();

    try {
        deliver($this, $this->form, $this->raw);
    } catch (Throwable) {
        // sync queue re-throws
    }

    $delivery = WebhookDelivery::query()->firstOrFail();
    expect($delivery->processed_at)->toBeNull()->and($delivery->error)->toContain('no current version');
});

test('submissions are scoped to the right hospital', function () {
    $other = Hospital::factory()->create();
    deliver($this, $this->form, $this->raw);

    expect(Submission::query()->forHospital($other)->count())->toBe(0)
        ->and(Submission::query()->forHospital($this->form->hospital)->count())->toBe(1);
});
