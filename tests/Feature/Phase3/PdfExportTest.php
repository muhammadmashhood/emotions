<?php

use App\Domain\Forms\FormVersioner;
use App\Domain\Submissions\SubmissionIngestor;
use App\Domain\Typeform\WebhookSignature;
use App\Enums\SubmissionSource;
use App\Http\Controllers\EvaluationPdfController;
use App\Models\Form;
use App\Models\Hospital;
use App\Models\Submission;
use App\Models\SubmissionAttachment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

test('an evaluation can be downloaded as PDF by its hospital and by admins, but not by other hospitals', function () {
    $hospital = Hospital::factory()->create();
    $form = Form::factory()->for($hospital)->create(['typeform_form_id' => 'rACXmOJ2']);
    app(FormVersioner::class)->createInitialVersion($form, fixture('form_definition_rACXmOJ2.json'));
    $raw = (string) file_get_contents(__DIR__.'/../../Fixtures/typeform/webhook_payload_rACXmOJ2.json');
    $this->call('POST', '/webhooks/typeform/'.$form->uuid, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_TYPEFORM_SIGNATURE' => WebhookSignature::sign($raw, $form->webhook_secret)], $raw)->assertOk();
    $submission = Submission::query()->firstOrFail();

    $response = $this->actingAs(viewer($hospital))->get(route('evaluations.pdf', $submission));
    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('evaluation-')
        ->and(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');

    $this->actingAs(superAdmin())->get(route('evaluations.pdf', $submission))->assertOk();
    $this->actingAs(viewer())->get(route('evaluations.pdf', $submission))->assertNotFound();

    // The detail page offers the download
    $this->actingAs(viewer($hospital))->get(route('evaluations.show', $submission))->assertSee('Download PDF');
})->group('slow');

test('the visit report embeds stored images and lists supporting documents', function () {
    Storage::fake('local');
    $hospital = Hospital::factory()->create();
    $form = Form::factory()->for($hospital)->create(['typeform_form_id' => 'rACXmOJ2']);
    app(FormVersioner::class)->createInitialVersion($form, fixture('form_definition_rACXmOJ2.json'));
    Http::fake(['*/responses/files/*' => Http::response('x', 200, ['Content-Type' => 'image/png'])]);
    $submission = app(SubmissionIngestor::class)->ingest($form, fixture('webhook_payload_rACXmOJ2.json')['form_response'], SubmissionSource::Webhook)->submission;
    $fileAnswer = $submission->answers()->where('answer_type', 'file_url')->sole();

    // Replace the downloaded bytes with a real PNG so the data URI is a valid image.
    $typeformCopy = $submission->attachments()->sole();
    Storage::disk('local')->put($typeformCopy->path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    $typeformCopy->update(['size' => 68]);
    SubmissionAttachment::factory()->create(['submission_id' => $submission->id, 'original_name' => 'Invoice 1234.pdf', 'uploaded_by' => superAdmin()->id]);
    SubmissionAttachment::factory()->png()->create(['submission_id' => $submission->id, 'original_name' => 'waiting-room.png']);

    $html = app(EvaluationPdfController::class)->html($submission);

    expect($html)->toContain('data:image/png;base64,')
        ->toContain('Supporting documents')
        ->toContain('Invoice 1234.pdf')
        ->toContain('waiting-room.png')
        ->toContain('Mystery Shopping Visit Report')
        ->not->toContain('Attachment: '.$fileAnswer->value_text);

    $this->actingAs(superAdmin())->get(route('evaluations.pdf', $submission))->assertOk()->assertHeader('content-type', 'application/pdf');
})->group('slow');
