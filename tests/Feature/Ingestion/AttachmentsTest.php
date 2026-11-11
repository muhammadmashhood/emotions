<?php

use App\Domain\Forms\FormVersioner;
use App\Domain\Submissions\AttachmentStore;
use App\Domain\Submissions\SubmissionIngestor;
use App\Enums\SubmissionSource;
use App\Livewire\Hospital\Evaluations\Show;
use App\Models\Form;
use App\Models\Hospital;
use App\Models\Submission;
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

test('SVG attachments are never served inline and file responses carry a sandboxing CSP', function () {
    $submission = Submission::factory()->create(['form_id' => $this->form->id, 'form_version_id' => $this->form->fresh()->currentVersion->id, 'hospital_id' => $this->hospital->id]);
    Storage::disk('local')->put('attachments/x/evil.svg', '<svg onload="alert(1)"/>');
    $svg = SubmissionAttachment::factory()->create(['submission_id' => $submission->id, 'path' => 'attachments/x/evil.svg', 'original_name' => 'evil.svg', 'mime' => 'image/svg+xml']);
    $png = SubmissionAttachment::factory()->png()->create(['submission_id' => $submission->id]);

    $this->actingAs(superAdmin())->get(route('evaluations.attachments.show', [$submission, $svg->id]))
        ->assertOk()
        ->assertHeader('content-type', 'application/octet-stream')
        ->assertHeader('content-security-policy', "default-src 'none'; sandbox")
        ->assertHeader('content-disposition', 'attachment; filename=evil.svg');
    $this->actingAs(superAdmin())->get(route('evaluations.attachments.show', [$submission, $png->id]))
        ->assertOk()->assertHeader('content-type', 'image/png')->assertHeader('content-security-policy', "default-src 'none'; sandbox");
});

test('staff uploads are limited to images and office documents', function () {
    $submission = Submission::factory()->create(['form_id' => $this->form->id, 'form_version_id' => $this->form->fresh()->currentVersion->id, 'hospital_id' => $this->hospital->id]);

    Livewire\Livewire::actingAs(superAdmin())->test(Show::class, ['evaluation' => $submission])
        ->set('documents', [UploadedFile::fake()->create('evil.svg', 1, 'image/svg+xml')])
        ->call('uploadDocuments')
        ->assertHasErrors(['documents.0'])
        ->set('documents', [UploadedFile::fake()->create('page.html', 1, 'text/html')])
        ->call('uploadDocuments')
        ->assertHasErrors(['documents.0']);

    expect(SubmissionAttachment::query()->count())->toBe(0);
});
