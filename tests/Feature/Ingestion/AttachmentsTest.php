<?php

use App\Domain\Forms\FormVersioner;
use App\Domain\Submissions\AttachmentStore;
use App\Domain\Submissions\SubmissionIngestor;
use App\Enums\SubmissionSource;
use App\Models\Form;
use App\Models\Hospital;
use App\Models\SubmissionAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->hospital = Hospital::factory()->create();
    $this->form = Form::factory()->for($this->hospital)->create(['typeform_form_id' => 'rACXmOJ2']);
    app(FormVersioner::class)->createInitialVersion($this->form, fixture('form_definition_rACXmOJ2.json'));
    $this->ingest = fn () => app(SubmissionIngestor::class)->ingest($this->form, fixture('webhook_payload_rACXmOJ2.json')['form_response'], SubmissionSource::Webhook)->submission;
});

test('file answers are downloaded from Typeform at ingest and kept on the private disk', function () {
    Http::fake(['*/responses/files/*' => Http::response('JPEGDATA', 200, ['Content-Type' => 'image/jpeg'])]);

    $submission = ($this->ingest)();

    $attachment = SubmissionAttachment::query()->where('submission_id', $submission->id)->sole();
    expect($attachment->source)->toBe('typeform')
        ->and($attachment->original_name)->toBe('receipt.jpg')
        ->and($attachment->mime)->toBe('image/jpeg')
        ->and($attachment->size)->toBe(8)
        ->and($attachment->path)->toStartWith('attachments/'.$submission->uuid.'/')
        ->and(Storage::disk('local')->get($attachment->path))->toBe('JPEGDATA')
        ->and($attachment->isImage())->toBeTrue();

    // Re-scoring or re-ingesting does not download again.
    expect(app(AttachmentStore::class)->syncFromTypeform($submission->fresh()))->toBe(0);
    Http::assertSentCount(1);
});

test('a failed Typeform download is logged and does not break ingestion', function () {
    Http::fake(['*/responses/files/*' => Http::response(['code' => 'UNAUTHORIZED'], 401)]);

    $submission = ($this->ingest)();

    expect($submission->overall_pct)->not->toBeNull()
        ->and(SubmissionAttachment::query()->count())->toBe(0);
});

test('the file route serves the stored copy without calling Typeform', function () {
    Http::fake(['*/responses/files/*' => Http::response('JPEGDATA', 200, ['Content-Type' => 'image/jpeg'])]);
    $submission = ($this->ingest)();
    $answer = $submission->answers()->where('answer_type', 'file_url')->sole();
    Http::fake([]); // any further request would be a stray

    $this->actingAs(viewer($this->hospital))->get(route('evaluations.files.show', [$submission, $answer->id]))
        ->assertOk()->assertHeader('content-type', 'image/jpeg');
    $attachment = SubmissionAttachment::query()->sole();
    $this->actingAs(viewer($this->hospital))->get(route('evaluations.attachments.show', [$submission, $attachment->id]))
        ->assertOk()->assertHeader('content-type', 'image/jpeg');
    $this->actingAs(viewer())->get(route('evaluations.attachments.show', [$submission, $attachment->id]))->assertNotFound();
});

test('staff can upload a supporting document to an evaluation', function () {
    Http::fake(['*/responses/files/*' => Http::response('x', 200, ['Content-Type' => 'image/png'])]);
    $submission = ($this->ingest)();

    $upload = app(AttachmentStore::class)->upload($submission, UploadedFile::fake()->create('invoice.pdf', 12, 'application/pdf'), superAdmin());

    expect($upload->source)->toBe('upload')
        ->and($upload->original_name)->toBe('invoice.pdf')
        ->and($upload->isImage())->toBeFalse()
        ->and(Storage::disk('local')->exists($upload->path))->toBeTrue()
        ->and($submission->attachments()->count())->toBe(2);

    app(AttachmentStore::class)->delete($upload);
    expect(Storage::disk('local')->exists($upload->path))->toBeFalse()->and($submission->attachments()->count())->toBe(1);
});
