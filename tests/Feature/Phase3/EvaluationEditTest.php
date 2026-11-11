<?php

use App\Domain\Forms\FormVersioner;
use App\Domain\Scoring\Rescorer;
use App\Domain\Typeform\WebhookSignature;
use App\Livewire\Hospital\Evaluations\Show;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Submission;
use App\Models\SubmissionAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->form = Form::factory()->create(['typeform_form_id' => 'rACXmOJ2']);
    $this->version = app(FormVersioner::class)->createInitialVersion($this->form, fixture('form_definition_rACXmOJ2.json'));
    $raw = (string) file_get_contents(__DIR__.'/../../Fixtures/typeform/webhook_payload_rACXmOJ2.json');
    $this->call('POST', '/webhooks/typeform/'.$this->form->uuid, [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_TYPEFORM_SIGNATURE' => WebhookSignature::sign($raw, $this->form->webhook_secret),
    ], $raw)->assertOk();
    $this->submission = Submission::query()->firstOrFail();
    $this->rings = FormField::query()->where('form_version_id', $this->version->id)->where('title', 'Was the call answered within 3 rings?')->firstOrFail();
    $answered = $this->submission->answers()->pluck('typeform_field_id')->all();
    $this->comment = FormField::query()->where('form_version_id', $this->version->id)->where('type', 'long_text')->whereIn('typeform_field_id', $answered)->get()->first(fn ($f) => Show::isCommentField($f));
});

test('staff edit a score and a comment; the evaluation is re-scored and the originals are kept', function () {
    expect($this->submission->overall_pct)->toBe(77.65);
    $answer = $this->submission->answers()->where('typeform_field_id', $this->rings->typeform_field_id)->firstOrFail();
    expect($answer->points)->toBe(10.0)->and($answer->max_points)->toBe(10.0);

    Livewire::actingAs(superAdmin())->test(Show::class, ['evaluation' => $this->submission])
        ->assertSee('Edit evaluation')
        ->call('startEditing')
        ->assertSet('editing', true)
        ->set('editPoints.'.$this->rings->typeform_field_id, '0')
        ->set('editText.'.$this->comment->typeform_field_id, 'Call was answered after six rings.')
        ->call('saveEdits')
        ->assertHasNoErrors()
        ->assertSet('editing', false)
        ->assertSee('Call was answered after six rings.')
        ->assertSee('edited');

    $this->submission->refresh();
    $edits = $this->submission->edits;
    expect($this->submission->answers()->where('typeform_field_id', $this->rings->typeform_field_id)->value('points'))->toBe(0.0)
        ->and((float) $this->submission->sectionScores()->where('section_name', 'Phone booking')->value('pct'))->toBe(60.0)
        ->and($this->submission->overall_pct)->toBeLessThan(77.65)
        ->and((float) $edits[$this->rings->typeform_field_id]['points'])->toBe(0.0)
        ->and((float) $edits[$this->rings->typeform_field_id]['original_points'])->toBe(10.0)
        ->and($edits[$this->comment->typeform_field_id]['text'])->toBe('Call was answered after six rings.')
        ->and($edits[$this->comment->typeform_field_id]['original_text'])->not->toBeNull()
        ->and($this->submission->answers()->where('typeform_field_id', $this->comment->typeform_field_id)->value('value_text'))->toBe('Call was answered after six rings.');
    $this->assertDatabaseHas('audit_logs', ['action' => 'submission.edited', 'subject_id' => $this->submission->id]);

    // A bulk re-score of the form keeps the edit.
    app(Rescorer::class)->rescoreForm($this->form);
    expect($this->submission->fresh()->answers()->where('typeform_field_id', $this->rings->typeform_field_id)->value('points'))->toBe(0.0);
});

test('points above the maximum or below zero are rejected', function () {
    Livewire::actingAs(superAdmin())->test(Show::class, ['evaluation' => $this->submission])
        ->call('startEditing')
        ->set('editPoints.'.$this->rings->typeform_field_id, '11')
        ->call('saveEdits')
        ->assertHasErrors(['editPoints.'.$this->rings->typeform_field_id]);
});

test('hospital users cannot edit an evaluation', function () {
    $admin = hospitalAdmin($this->form->hospital);
    expect($admin->can('edit', $this->submission))->toBeFalse();

    Livewire::actingAs($admin)->test(Show::class, ['evaluation' => $this->submission])
        ->assertDontSee('Edit evaluation')
        ->call('startEditing')
        ->assertForbidden();
});

test('staff upload supporting documents that hospital users then see', function () {
    Livewire::actingAs(superAdmin())->test(Show::class, ['evaluation' => $this->submission])
        ->set('documents', [UploadedFile::fake()->create('Invoice 1234.pdf', 20, 'application/pdf'), UploadedFile::fake()->image('sms.png')])
        ->call('uploadDocuments')
        ->assertHasNoErrors()
        ->assertSee('Invoice 1234.pdf')
        ->assertSee('sms.png');

    expect(SubmissionAttachment::query()->where('source', 'upload')->count())->toBe(2);
    $this->assertDatabaseHas('audit_logs', ['action' => 'submission.document_added', 'subject_id' => $this->submission->id]);

    Livewire::actingAs(hospitalAdmin($this->form->hospital))->test(Show::class, ['evaluation' => $this->submission])
        ->assertSee('Supporting documents')
        ->assertSee('Invoice 1234.pdf');

    $doc = SubmissionAttachment::query()->where('original_name', 'Invoice 1234.pdf')->sole();
    Livewire::actingAs(superAdmin())->test(Show::class, ['evaluation' => $this->submission])
        ->call('deleteDocument', $doc->id)
        ->assertDontSee('Invoice 1234.pdf');
    expect(SubmissionAttachment::query()->find($doc->id))->toBeNull();
});
