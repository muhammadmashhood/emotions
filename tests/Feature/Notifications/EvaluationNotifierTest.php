<?php

use App\Domain\Submissions\EvaluationNotifier;
use App\Mail\EvaluationPublishedMail;
use App\Mail\EvaluationSubmittedMail;
use App\Models\Form;
use App\Models\FormVersion;
use App\Models\Hospital;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->admin = superAdmin();
    $this->hospital = Hospital::factory()->create(['name' => 'Ittefaq Hospital']);
    // The form factory would otherwise create a second super admin as linked_by.
    $this->form = Form::factory()->for($this->hospital)->create(['title' => 'OPD survey', 'linked_by' => $this->admin->id]);
    $version = FormVersion::factory()->create(['form_id' => $this->form->id]);
    $this->submission = Submission::factory()->withScore(82.5)->create([
        'form_id' => $this->form->id,
        'form_version_id' => $version->id,
        'hospital_id' => $this->hospital->id,
        'submitted_at' => now()->subHour(),
        'info' => ['facility' => 'Main campus', 'visit_date' => '2026-11-10'],
    ]);

    $this->inactiveAdmin = User::factory()->superAdmin()->inactive()->create();
    $this->qa = User::factory()->qa()->create();
    $this->hospitalAdmin = hospitalAdmin($this->hospital);
    $this->viewer = viewer($this->hospital);
    $this->otherAdmin = hospitalAdmin(Hospital::factory()->create());
    $this->inactiveHospitalAdmin = hospitalAdmin(Hospital::factory()->create(['is_active' => false]));
    $this->inactiveHospitalUser = User::factory()->hospitalAdmin($this->hospital)->inactive()->create();
});

test('submitted goes to active staff only, one mail each', function () {
    Mail::fake();

    app(EvaluationNotifier::class)->submitted($this->submission);

    Mail::assertQueued(EvaluationSubmittedMail::class, 2);
    Mail::assertQueued(EvaluationSubmittedMail::class, fn ($m) => $m->hasTo($this->admin->email));
    Mail::assertQueued(EvaluationSubmittedMail::class, fn ($m) => $m->hasTo($this->qa->email));
    Mail::assertNotQueued(EvaluationSubmittedMail::class, fn ($m) => $m->hasTo($this->inactiveAdmin->email) || $m->hasTo($this->hospitalAdmin->email));
    Mail::assertNotQueued(EvaluationPublishedMail::class);
});

test("published goes to the hospital's active admins only", function () {
    Mail::fake();

    app(EvaluationNotifier::class)->published($this->submission);

    Mail::assertQueued(EvaluationPublishedMail::class, 1);
    Mail::assertQueued(EvaluationPublishedMail::class, fn ($m) => $m->hasTo($this->hospitalAdmin->email));
    Mail::assertNotQueued(EvaluationPublishedMail::class, fn ($m) => $m->hasTo($this->viewer->email)
        || $m->hasTo($this->otherAdmin->email)
        || $m->hasTo($this->inactiveHospitalAdmin->email)
        || $m->hasTo($this->inactiveHospitalUser->email)
        || $m->hasTo($this->admin->email));
    Mail::assertNotQueued(EvaluationSubmittedMail::class);
});

test('emails carry the summary and a link', function () {
    $url = route('evaluations.show', $this->submission);

    $submitted = (new EvaluationSubmittedMail($this->submission))->render();
    expect($submitted)->toContain('Ittefaq Hospital', 'OPD survey', 'Main campus', '82.5', $url);
    expect((new EvaluationSubmittedMail($this->submission))->envelope()->subject)->toBe('New evaluation: Ittefaq Hospital · OPD survey');

    $published = (new EvaluationPublishedMail($this->submission))->render();
    expect($published)->toContain('OPD survey', 'Main campus', '82.5', $url);
    expect((new EvaluationPublishedMail($this->submission))->envelope()->subject)->toBe('Evaluation published: OPD survey');
});

test('an unscored evaluation says so instead of a number', function () {
    $this->submission->forceFill(['overall_pct' => null, 'points' => null, 'max_points' => null, 'rag' => null])->save();

    expect((new EvaluationSubmittedMail($this->submission->fresh()))->render())->toContain('Not scored');
});

test('a mail failure is logged per recipient, not thrown', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp down'));
    Log::shouldReceive('warning')->twice()->withArgs(fn ($message) => $message === 'evaluation.notify_failed');

    app(EvaluationNotifier::class)->submitted($this->submission);
});
