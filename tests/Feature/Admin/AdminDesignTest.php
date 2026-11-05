<?php

use App\Livewire\Admin\Hospitals\Show as HospitalShow;
use App\Livewire\Admin\Review;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormVersion;
use App\Models\Hospital;
use App\Models\Section;
use App\Models\Submission;
use App\Models\UserInvitation;
use Livewire\Livewire;

test('the admin sidebar shows the design navigation, the review count and the hospital switcher', function () {
    $hospital = Hospital::factory()->requiresReview()->create(['name' => 'Ittefaq Hospital']);
    $form = Form::factory()->for($hospital)->create();
    $version = FormVersion::factory()->create(['form_id' => $form->id]);
    Submission::factory()->pendingReview()->count(2)->create(['form_id' => $form->id, 'form_version_id' => $version->id, 'hospital_id' => $hospital->id]);

    $this->actingAs(superAdmin())->get(route('admin.hospitals.show', $hospital))
        ->assertOk()
        ->assertSee('theme-admin', false)
        ->assertSeeInOrder(['Overview', 'Hospitals', 'Review queue', '2', 'Users &amp; invitations', 'Deliveries &amp; audit'], false)
        ->assertSee('Jump to a hospital')
        ->assertSee('<option value="'.route('admin.hospitals.show', $hospital).'" selected', false)
        ->assertSee(route('hospitals.dashboard', $hospital), false);
});

test('hospital users keep their own navigation and palette', function () {
    $this->actingAs(hospitalAdmin())->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('theme-admin', false)
        ->assertDontSee('Jump to a hospital');
});

test('the review queue flags low scores and blank info fields the form maps', function () {
    $hospital = Hospital::factory()->requiresReview()->create();
    $form = Form::factory()->for($hospital)->create(['title' => 'OPD survey']);
    $version = FormVersion::factory()->create(['form_id' => $form->id]);
    $section = Section::factory()->create(['form_version_id' => $version->id]);
    foreach (['facility', 'doctor'] as $key) {
        FormField::factory()->text()->create(['form_version_id' => $version->id, 'section_id' => $section->id, 'info_key' => $key, 'is_scored' => false]);
    }
    Submission::factory()->pendingReview()->create([
        'form_id' => $form->id, 'form_version_id' => $version->id, 'hospital_id' => $hospital->id,
        'overall_pct' => 48.6, 'info' => ['facility' => 'Al Barsha Clinic', 'doctor' => ''],
    ]);

    Livewire::actingAs(superAdmin())->test(Review::class)
        ->assertSee('Al Barsha Clinic')
        ->assertSee('OPD survey')
        ->assertSee('48.6%')
        ->assertSee('Low score')
        ->assertSee('Doctor name blank')
        ->assertDontSee('Facility missing')
        ->assertDontSee('Missing visit date') // not mapped by this form
        ->assertSee('Review →');
});

test('users and pending invitations share one table, and sending an invite closes the dialog', function () {
    $hospital = Hospital::factory()->create(['name' => 'Ittefaq Hospital']);
    UserInvitation::factory()->create(['email' => 'pending@ittefaq.test', 'hospital_id' => $hospital->id]);

    Livewire::actingAs(superAdmin())->test(UsersIndex::class)
        ->assertSee('pending@ittefaq.test')
        ->assertSee('Invited')
        ->assertSee('Resend')
        ->set('search', 'nobody-matches')
        ->assertDontSee('pending@ittefaq.test')
        ->set('search', '')
        ->set('inviteEmail', 'new@ittefaq.test')
        ->set('inviteRole', 'hospital_admin')
        ->set('inviteHospital', $hospital->uuid)
        ->call('invite')
        ->assertHasNoErrors()
        ->assertDispatched('invite-sent');
});

test('the hospital page lists facilities with their evaluation counts', function () {
    $hospital = Hospital::factory()->create();
    Branch::factory()->create(['hospital_id' => $hospital->id, 'name' => 'Jumeirah Clinic', 'code' => 'JUM']);

    Livewire::actingAs(superAdmin())->test(HospitalShow::class, ['hospital' => $hospital])
        ->assertSee('Setup & attention')
        ->assertSee('No form')
        ->set('tab', 'facilities')
        ->assertSee('Jumeirah Clinic')
        ->assertSee('JUM');
});

test('audit entries have readable labels and summaries', function () {
    $hospital = Hospital::factory()->create(['name' => 'CMH']);

    expect(new AuditLog(['action' => 'mapping.updated', 'changes' => ['title' => 'OPD']]))
        ->label()->toBe('Saved mapping')
        ->and(tap(new AuditLog(['action' => 'mapping.updated', 'changes' => ['title' => 'OPD']]), fn ($l) => $l->setRelation('hospital', $hospital))->summary())->toBe('OPD · CMH')
        ->and((new AuditLog(['action' => 'auth.login']))->summary())->toBe('eMotion')
        ->and((new AuditLog(['action' => 'something.new_thing']))->label())->toBe('Something new thing');
});
