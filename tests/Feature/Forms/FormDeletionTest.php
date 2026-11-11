<?php

use App\Enums\FormStatus;
use App\Livewire\Admin\Forms\Show as FormShow;
use App\Livewire\Admin\Hospitals\Show as HospitalShow;
use App\Models\Answer;
use App\Models\AuditLog;
use App\Models\Form;
use App\Models\FormVersion;
use App\Models\Hospital;
use App\Models\Submission;
use App\Models\SubmissionSectionScore;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->hospital = Hospital::factory()->create();
    $this->form = Form::factory()->for($this->hospital)->create(['typeform_form_id' => 'F1', 'title' => 'OPD survey']);
    $this->version = FormVersion::factory()->create(['form_id' => $this->form->id]);
    $this->submission = Submission::factory()->create(['form_id' => $this->form->id, 'form_version_id' => $this->version->id, 'hospital_id' => $this->hospital->id]);
    Answer::factory()->create(['submission_id' => $this->submission->id]);
    $this->delivery = WebhookDelivery::factory()->create(['form_id' => $this->form->id]);
});

test('the hospital Forms tab can unlink a form', function () {
    Http::fake(['*/forms/F1/webhooks/*' => Http::response(null, 204)]);

    Livewire::actingAs(superAdmin())->test(HospitalShow::class, ['hospital' => $this->hospital])
        ->set('tab', 'forms')
        ->assertSee('Unlink')
        ->call('unlinkForm', $this->form->id)
        ->assertHasNoErrors();

    expect($this->form->fresh()->status)->toBe(FormStatus::Unlinked)
        ->and(Submission::query()->find($this->submission->id))->not->toBeNull();
    Http::assertSent(fn ($request) => $request->method() === 'DELETE');
});

test('deleting a form from the Forms tab removes its versions and evaluations and keeps delivery history', function () {
    Http::fake(['*/forms/F1/webhooks/*' => Http::response(null, 204)]);

    Livewire::actingAs(superAdmin())->test(HospitalShow::class, ['hospital' => $this->hospital])
        ->set('tab', 'forms')
        ->assertSee('Delete')
        ->call('deleteForm', $this->form->id)
        ->assertHasNoErrors();

    expect(Form::withTrashed()->find($this->form->id))->toBeNull()
        ->and(FormVersion::query()->find($this->version->id))->toBeNull()
        ->and(Submission::query()->find($this->submission->id))->toBeNull()
        ->and(Answer::query()->count())->toBe(0)
        ->and(SubmissionSectionScore::query()->count())->toBe(0)
        ->and($this->delivery->fresh()->form_id)->toBeNull()
        ->and(AuditLog::query()->where('action', 'form.deleted')->exists())->toBeTrue();
});

test('the form page can delete the form and returns to the hospital', function () {
    Http::fake(['*/forms/F1/webhooks/*' => Http::response(null, 204)]);

    Livewire::actingAs(superAdmin())->test(FormShow::class, ['form' => $this->form])
        ->assertSee('Delete form')
        ->call('delete')
        ->assertRedirect(route('admin.hospitals.show', ['hospital' => $this->hospital, 'tab' => 'forms']));

    expect(Form::withTrashed()->find($this->form->id))->toBeNull();
});

test('a hospital admin cannot delete a form', function () {
    expect(hospitalAdmin($this->hospital)->can('delete', $this->form))->toBeFalse();
});
