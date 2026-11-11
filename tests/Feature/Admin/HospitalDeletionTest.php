<?php

use App\Livewire\Admin\Hospitals\Show as HospitalShow;
use App\Models\Answer;
use App\Models\AuditLog;
use App\Models\Form;
use App\Models\FormVersion;
use App\Models\Hospital;
use App\Models\Submission;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    $this->hospital = Hospital::factory()->create(['name' => 'Old Clinic', 'logo_path' => 'logos/old.png']);
    Storage::disk('public')->put('logos/old.png', 'png');
    $this->form = Form::factory()->for($this->hospital)->create(['typeform_form_id' => 'F1']);
    $version = FormVersion::factory()->create(['form_id' => $this->form->id]);
    $this->submission = Submission::factory()->create(['form_id' => $this->form->id, 'form_version_id' => $version->id, 'hospital_id' => $this->hospital->id]);
    Answer::factory()->create(['submission_id' => $this->submission->id]);
    $this->user = User::factory()->hospitalAdmin($this->hospital)->create();
    UserInvitation::factory()->create(['hospital_id' => $this->hospital->id]);
});

test('deleting a hospital removes its forms, evaluations, users, invitations and logo, and drops the Typeform webhook', function () {
    Http::fake(['*/forms/F1/webhooks/*' => Http::response(null, 204), '*/workspaces*' => Http::response(['items' => [], 'total_items' => 0])]);

    Livewire::actingAs(superAdmin())->test(HospitalShow::class, ['hospital' => $this->hospital])
        ->set('tab', 'settings')
        ->assertSee('Danger zone')
        ->set('confirmDeleteName', 'Old Clinic')
        ->call('deleteHospital')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.hospitals.index'));

    expect(Hospital::query()->find($this->hospital->id))->toBeNull()
        ->and(Form::withTrashed()->find($this->form->id))->toBeNull()
        ->and(Submission::query()->find($this->submission->id))->toBeNull()
        ->and(Answer::query()->count())->toBe(0)
        ->and(User::query()->find($this->user->id))->toBeNull()
        ->and(UserInvitation::query()->where('hospital_id', $this->hospital->id)->count())->toBe(0)
        ->and(Storage::disk('public')->exists('logos/old.png'))->toBeFalse()
        ->and(AuditLog::query()->where('action', 'hospital.deleted')->exists())->toBeTrue();

    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), '/forms/F1/webhooks/'));
});

test('the confirmation must match the hospital name exactly', function () {
    Livewire::actingAs(superAdmin())->test(HospitalShow::class, ['hospital' => $this->hospital])
        ->set('confirmDeleteName', 'old clinic')
        ->call('deleteHospital')
        ->assertHasErrors(['confirmDeleteName']);

    expect(Hospital::query()->find($this->hospital->id))->not->toBeNull();
});

test('a revoked Typeform token does not block deleting the hospital', function () {
    Http::fake(['*/forms/F1/webhooks/*' => Http::response(['code' => 'UNAUTHORIZED'], 401)]);

    Livewire::actingAs(superAdmin())->test(HospitalShow::class, ['hospital' => $this->hospital])
        ->set('confirmDeleteName', 'Old Clinic')
        ->call('deleteHospital')
        ->assertHasNoErrors();

    expect(Hospital::query()->find($this->hospital->id))->toBeNull();
});

test('a hospital admin cannot delete a hospital', function () {
    expect($this->user->can('delete', $this->hospital))->toBeFalse();
});
