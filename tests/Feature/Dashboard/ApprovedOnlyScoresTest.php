<?php

use App\Domain\Dashboard\DashboardFilters;
use App\Domain\Dashboard\DashboardQuery;
use App\Domain\Forms\FormVersioner;
use App\Enums\Rag;
use App\Enums\SubmissionStatus;
use App\Models\Form;
use App\Models\Hospital;
use App\Models\Submission;
use App\Models\SubmissionSectionScore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Scores and counts must come from approved evaluations only, for every viewer, so staff and hospital figures match.
 */
beforeEach(function () {
    CarbonImmutable::setTestNow('2026-06-15 12:00:00');
    Carbon::setTestNow('2026-06-15 12:00:00');

    $this->hospital = Hospital::factory()->create();
    $this->form = Form::factory()->for($this->hospital)->create(['title' => 'OPD']);
    $this->version = app(FormVersioner::class)->createInitialVersion($this->form, fixture('form_rating_yesno.json'));
    $this->form->update(['title' => 'OPD']);
    $section = $this->version->sections()->firstOrFail();

    $this->make = function (string $status, float $pct, array $extra = []) use ($section): Submission {
        $s = Submission::factory()->create(array_merge([
            'form_id' => $this->form->id,
            'form_version_id' => $this->version->id,
            'hospital_id' => $this->hospital->id,
            'submitted_at' => now()->subDays(3),
            'overall_pct' => $pct,
            'rag' => Rag::fromPct($pct, 80, 60),
            'info' => ['facility' => 'Main'],
        ], $extra));
        $s->forceFill(['status' => $status])->save();
        SubmissionSectionScore::factory()->create(['submission_id' => $s->id, 'section_id' => $section->id, 'section_name' => $section->name, 'pct' => $pct, 'points' => $pct, 'max_points' => 100]);

        return $s;
    };

    ($this->make)(SubmissionStatus::Approved->value, 80);
    ($this->make)(SubmissionStatus::Approved->value, 60);
    $this->rejected = ($this->make)(SubmissionStatus::Rejected->value, 20);
    ($this->make)(SubmissionStatus::PendingReview->value, 100);
});

test('staff headline and per-hospital numbers match the hospital view and skip rejected and pending', function () {
    $filters = DashboardFilters::fromRequest('30d');
    $staff = (new DashboardQuery(superAdmin()))->headline($this->hospital, $filters);
    $client = (new DashboardQuery(viewer($this->hospital)))->headline($this->hospital, $filters);

    expect($staff['avg'])->toBe(70.0)->and($staff['count'])->toBe(2)->and($staff)->toBe($client);
    expect((new DashboardQuery(superAdmin()))->perHospital($filters)[$this->hospital->id]['avg'])->toBe(70.0);
    expect((new DashboardQuery(superAdmin()))->distribution($this->hospital, $filters))->toBe(['green' => 1, 'amber' => 1, 'red' => 0, 'unscored' => 0]);
});

test('heatmap cell rows exclude rejected surveys for staff', function () {
    $cell = (new DashboardQuery(superAdmin()))->heatmapCell($this->hospital, DashboardFilters::fromRequest('30d'), null, 'Main', 'OPD');

    expect($cell['count'])->toBe(2)->and(collect($cell['rows'])->pluck('pct')->all())->toBe([60.0, 80.0]);
});

test('re-approving a rejected survey counts it again', function () {
    $this->rejected->forceFill(['status' => SubmissionStatus::Approved])->save();

    expect((new DashboardQuery(superAdmin()))->headline($this->hospital, DashboardFilters::fromRequest('30d'))['count'])->toBe(3);
});

test('partial rule applies to staff numbers', function () {
    ($this->make)(SubmissionStatus::Approved->value, 40, ['is_partial' => true]);

    expect((new DashboardQuery(superAdmin()))->headline($this->hospital, DashboardFilters::fromRequest('30d'))['count'])->toBe(2);
});

test('staff dashboard with only pending surveys renders empty', function () {
    Submission::query()->whereIn('status', [SubmissionStatus::Approved->value, SubmissionStatus::Rejected->value])->delete();

    $this->actingAs(superAdmin())->get(route('hospitals.dashboard', $this->hospital))->assertOk()->assertDontSee('70.0');
});
