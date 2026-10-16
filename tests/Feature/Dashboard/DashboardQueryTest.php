<?php

use App\Domain\Dashboard\DashboardFilters;
use App\Domain\Dashboard\DashboardQuery;
use App\Domain\Forms\FormVersioner;
use App\Enums\Rag;
use App\Models\Answer;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormVersion;
use App\Models\Hospital;
use App\Models\Submission;
use App\Models\SubmissionSectionScore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Hand-calculated dataset. "Now" is frozen at 2026-06-15 so buckets are stable.
 */
beforeEach(function () {
    CarbonImmutable::setTestNow('2026-06-15 12:00:00');
    Carbon::setTestNow('2026-06-15 12:00:00');

    $this->hospital = Hospital::factory()->create();
    $this->form = Form::factory()->for($this->hospital)->create(['title' => 'OPD']);
    $this->version = app(FormVersioner::class)->createInitialVersion($this->form, fixture('form_rating_yesno.json'));
    $this->form->update(['title' => 'OPD']);
    $this->otherForm = Form::factory()->for($this->hospital)->create(['title' => 'ER']);
    $otherVersion = FormVersion::factory()->create(['form_id' => $this->otherForm->id]);

    $section = $this->version->sections()->firstOrFail();           // "Experience"
    $this->stars = FormField::query()->where('form_version_id', $this->version->id)->where('title', 'Stars')->firstOrFail();
    $this->back = FormField::query()->where('form_version_id', $this->version->id)->where('title', 'Would you come back?')->firstOrFail();
    $this->clean = FormField::query()->where('form_version_id', $this->version->id)->where('title', 'Clean?')->firstOrFail();

    $make = function (string $date, float $pct, Form $form, ?array $answers = null) use ($section, $otherVersion) {
        $s = Submission::factory()->create([
            'form_id' => $form->id,
            'form_version_id' => $form->is($this->form) ? $this->version->id : $otherVersion->id,
            'hospital_id' => $this->hospital->id,
            'submitted_at' => $date,
            'overall_pct' => $pct,
            'rag' => Rag::fromPct($pct, 80, 60),
            'info' => ['facility' => 'Facility '.$pct, 'doctor' => 'Dr X'],
        ]);
        if ($form->is($this->form)) {
            SubmissionSectionScore::factory()->create(['submission_id' => $s->id, 'section_id' => $section->id, 'section_name' => $section->name, 'pct' => $pct, 'points' => $pct, 'max_points' => 100]);
            foreach ($answers ?? [] as [$field, $points, $max]) {
                Answer::factory()->create(['submission_id' => $s->id, 'typeform_field_id' => $field->typeform_field_id, 'form_field_id' => $field->id, 'points' => $points, 'max_points' => $max, 'answer_type' => $points === null ? 'choice' : 'number']);
            }
        }

        return $s;
    };

    // Current 90 days: 2026-03-18 .. 2026-06-15
    $make('2026-06-10 10:00', 90, $this->form, [[$this->stars, 4, 4], [$this->back, 10, 10], [$this->clean, 10, 10]]);
    $make('2026-06-03 10:00', 70, $this->form, [[$this->stars, 2, 4], [$this->back, 0, 10], [$this->clean, null, null]]);
    $make('2026-05-20 10:00', 50, $this->form, [[$this->stars, 1, 4], [$this->back, 10, 10], [$this->clean, 0, 10]]);
    $make('2026-04-01 10:00', 80, $this->otherForm);
    // Previous 90 days: 2025-12-18 .. 2026-03-17
    $make('2026-02-01 10:00', 60, $this->form);
    $make('2026-01-15 10:00', 40, $this->otherForm);
    // Outside both
    $make('2025-11-01 10:00', 10, $this->form);

    // Other hospital noise, must never leak
    $other = Form::factory()->create();
    Submission::factory()->create(['form_id' => $other->id, 'form_version_id' => FormVersion::factory()->create(['form_id' => $other->id])->id, 'hospital_id' => $other->hospital_id, 'submitted_at' => '2026-06-01', 'overall_pct' => 100]);

    $this->query = new DashboardQuery(viewer($this->hospital));
    $this->filters = DashboardFilters::fromRequest('90d');
});

afterEach(fn () => CarbonImmutable::setTestNow());

test('headline: average, count and change vs the previous equal period', function () {
    $h = $this->query->headline($this->hospital, $this->filters);

    expect($h)->toBe(['avg' => 72.5, 'count' => 4, 'previous_avg' => 50.0, 'previous_count' => 2, 'delta' => 22.5]);

    $single = $this->query->headline($this->hospital, $this->filters->withForm($this->form->id));
    expect($single['avg'])->toBe(70.0)->and($single['count'])->toBe(3)->and($single['delta'])->toBe(10.0);
});

test('trend buckets per week, one line per form when all forms are selected', function () {
    $t = $this->query->trend($this->hospital, $this->filters);

    expect($t['bucket'])->toBe('week')
        ->and($t['labels'][0])->toBe('2026-W12')
        ->and(end($t['labels']))->toBe('2026-W25')
        ->and(collect($t['series'])->pluck('label')->all())->toBe(['OPD', 'ER']);

    $opd = collect($t['series'])->firstWhere('label', 'OPD');
    $w24 = array_search('2026-W24', $t['labels'], true); // 2026-06-10
    $w23 = array_search('2026-W23', $t['labels'], true); // 2026-06-03
    expect($opd['data'][$w24])->toBe(90.0)->and($opd['data'][$w23])->toBe(70.0)->and($opd['counts'][$w24])->toBe(1);

    $single = $this->query->trend($this->hospital, $this->filters->withForm($this->form->id));
    expect($single['series'])->toHaveCount(1)->and($single['series'][0]['data'][$w24])->toBe(90.0);

    $year = $this->query->trend($this->hospital, DashboardFilters::fromRequest('12m'));
    expect($year['bucket'])->toBe('month')->and($year['labels'])->toContain('2026-06')->and($year['labels'][0])->toBe('2025-06');
    $june = array_search('2026-06', $year['labels'], true);
    expect(collect($year['series'])->firstWhere('label', 'OPD')['data'][$june])->toBe(80.0); // (90+70)/2
});

test('section averages, weakest questions and yes/no compliance for a single form', function () {
    $sections = $this->query->sectionAverages($this->form, $this->filters);
    expect($sections)->toBe([['name' => 'Experience', 'avg' => 70.0, 'count' => 3]]);

    $weakest = $this->query->weakestQuestions($this->form, $this->filters);
    // Stars: (4+2+1)/12 = 58.3; Would you come back: 20/30 = 66.7; Clean: 10/20 = 50
    expect(collect($weakest)->pluck('avg_pct', 'title')->all())->toBe(['Clean?' => 50.0, 'Stars' => 58.3, 'Would you come back?' => 66.7])
        ->and($weakest[0]['count'])->toBe(2)
        ->and($weakest[0]['section'])->toBe('Experience');

    $compliance = $this->query->yesNoCompliance($this->form, $this->filters);
    expect(collect($compliance)->keyBy('title')->map(fn ($c) => [$c['yes'], $c['no'], $c['pct_yes']])->all())->toBe([
        'Would you come back?' => [2, 1, 66.7],
        'Clean?' => [1, 1, 50.0],   // the NA answer carries no points and is excluded
    ]);
});

test('distribution and recent evaluations', function () {
    expect($this->query->distribution($this->hospital, $this->filters))->toBe(['green' => 2, 'amber' => 1, 'red' => 1, 'unscored' => 0]);

    $recent = $this->query->recent($this->hospital, $this->filters, 2);
    expect($recent)->toHaveCount(2)->and((float) $recent->first()->overall_pct)->toBe(90.0);
});

test('a super admin sees pending and partial evaluations in the numbers, hospital users do not', function () {
    Submission::factory()->pendingReview()->create(['form_id' => $this->form->id, 'form_version_id' => $this->version->id, 'hospital_id' => $this->hospital->id, 'submitted_at' => '2026-06-12', 'overall_pct' => 0]);

    expect($this->query->headline($this->hospital, $this->filters)['count'])->toBe(4);
    expect((new DashboardQuery(superAdmin()))->headline($this->hospital, $this->filters)['count'])->toBe(5);
});

test('per-hospital summary for the admin overview', function () {
    $per = (new DashboardQuery(superAdmin()))->perHospital($this->filters);
    expect($per[$this->hospital->id])->toBe(['count' => 4, 'avg' => 72.5, 'previous_avg' => 50.0]);
});

test('filters: presets and custom ranges', function () {
    $f = DashboardFilters::fromRequest('30d');
    expect($f->from->toDateString())->toBe('2026-05-17')->and($f->to->toDateString())->toBe('2026-06-15')->and($f->lengthInDays())->toBe(30)
        ->and($f->previous()->from->toDateString())->toBe('2026-04-17')->and($f->previous()->to->toDateString())->toBe('2026-05-16');

    $c = DashboardFilters::fromRequest('custom', '2026-03-01', '2026-01-01');
    expect($c->from->toDateString())->toBe('2026-01-01')->and($c->to->toDateString())->toBe('2026-03-01')->and($c->bucket())->toBe('week');
    expect(DashboardFilters::fromRequest('12m')->bucket())->toBe('month');
});
