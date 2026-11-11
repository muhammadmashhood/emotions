<?php

use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\Hospital\Evaluations\Show as EvaluationShow;
use App\Models\Form;
use App\Models\FormVersion;
use App\Models\Hospital;
use App\Models\Submission;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function qaUser(): User
{
    return User::factory()->qa()->create();
}

beforeEach(function () {
    $this->hospital = Hospital::factory()->requiresReview()->create(['name' => 'NMC Test']);
    $form = Form::factory()->for($this->hospital)->create();
    $version = FormVersion::factory()->create(['form_id' => $form->id]);
    $this->submission = Submission::factory()->pendingReview()->create(['form_id' => $form->id, 'form_version_id' => $version->id, 'hospital_id' => $this->hospital->id]);
});

test('a QA user reaches the review queue and every hospital dashboard', function () {
    $qa = qaUser();
    $this->actingAs($qa)->get(route('home'))->assertRedirect(route('admin.review'));
    $this->actingAs($qa)->get(route('admin.review'))->assertOk()->assertSee('NMC Test')->assertSee('eMotion QA');
    $this->actingAs($qa)->get(route('hospitals.dashboard', $this->hospital))->assertOk();
    $this->actingAs($qa)->get(route('hospitals.evaluations.index', $this->hospital))->assertOk();
    $this->actingAs($qa)->get(route('evaluations.show', $this->submission))->assertOk()->assertSee('Admin tools');
});

test('a QA user cannot manage hospitals, users, forms or settings', function () {
    $qa = qaUser();
    foreach (['admin.overview', 'admin.hospitals.index', 'admin.users.index', 'admin.settings.typeform', 'admin.deliveries'] as $route) {
        $this->actingAs($qa)->get(route($route))->assertForbidden();
    }
    $this->actingAs($qa)->get(route('admin.hospitals.show', $this->hospital))->assertForbidden();
    $this->actingAs($qa)->get(route('admin.review'))->assertDontSee('Users &amp; invitations', false)->assertDontSee('Typeform connection');
});

test('a QA user can approve an evaluation', function () {
    Livewire::actingAs(qaUser())->test(EvaluationShow::class, ['evaluation' => $this->submission])
        ->set('reviewNote', 'Checked')
        ->call('approve')
        ->assertHasNoErrors();

    expect($this->submission->fresh()->status)->toBe(SubmissionStatus::Approved);
});

test('a super admin invites an eMotion QA without a hospital', function () {
    Mail::fake();
    Livewire::actingAs(superAdmin())
        ->test(UsersIndex::class)
        ->assertSee('eMotion QA')
        ->set('inviteEmail', 'qa@e-motion.ae')
        ->set('inviteRole', 'emotion_qa')
        ->set('inviteHospital', '')
        ->call('invite')
        ->assertHasNoErrors();

    $invitation = UserInvitation::query()->where('email', 'qa@e-motion.ae')->firstOrFail();
    expect($invitation->role)->toBe(Role::QualityAssurance)->and($invitation->hospital_id)->toBeNull();
});

test('a hospital admin cannot invite a QA user', function () {
    Mail::fake();
    $admin = hospitalAdmin($this->hospital);
    expect($admin->can('invite', [User::class, Role::QualityAssurance, $this->hospital]))->toBeFalse();
});
