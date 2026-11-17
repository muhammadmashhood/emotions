<?php

use App\Http\Controllers\EvaluationPdfController;
use App\Mail\UserInvitationMail;
use App\Models\Form;
use App\Models\FormVersion;
use App\Models\Hospital;
use App\Models\Submission;
use App\Models\UserInvitation;

test('the logo assets exist in public/images', function () {
    foreach (['emotion-logo.png', 'emotion-logo-white.png', 'emotion-mark.png'] as $file) {
        expect(file_exists(public_path('images/'.$file)))->toBeTrue($file.' missing');
    }
    expect(filesize(public_path('favicon.ico')))->toBeGreaterThan(0);
});

test('the sign-in page shows the white logo and the favicon', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('images/emotion-logo-white.png')
        ->assertSee('rel="icon"', false)
        ->assertDontSee('>MOTION<', false);
});

test('the app shell shows the logo for hospital users and admins', function () {
    $hospital = Hospital::factory()->create();
    $this->actingAs(viewer($hospital))->get(route('dashboard'))->assertOk()->assertSee('images/emotion-logo.png')->assertDontSee('>MOTION<', false);
    $this->actingAs(superAdmin())->get(route('admin.overview'))->assertOk()->assertSee('images/emotion-logo.png')->assertDontSee('>MOTION<', false);
});

test('the visit report carries the eMotion logo', function () {
    $hospital = Hospital::factory()->create();
    $form = Form::factory()->for($hospital)->create();
    $version = FormVersion::factory()->for($form)->create();
    $submission = Submission::factory()->create(['form_id' => $form->id, 'form_version_id' => $version->id, 'hospital_id' => $hospital->id]);

    expect(app(EvaluationPdfController::class)->html($submission))->toContain('alt="eMotion"')->toContain('data:image/png;base64,');
});

test('the invitation email shows the logo', function () {
    $invitation = UserInvitation::factory()->create();
    $html = (new UserInvitationMail($invitation, 'https://example.test/accept'))->render();

    expect($html)->toContain('images/emotion-logo.png');
});
