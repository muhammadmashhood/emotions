# Approved-only Scores Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every score, average and count in the app is computed from approved evaluations only, for every viewer, while staff keep track of rejected surveys.

**Architecture:** All aggregates flow through `DashboardQuery::base()`; applying the existing `hospitalVisible()` scope there for every viewer fixes every number at once. Lists (`EvaluationListQuery`) are untouched so staff still see pending and rejected rows. Two small staff-facing additions: a "Rejected surveys" KPI on the admin overview and a Status column in the staff CSV export.

**Tech Stack:** Laravel 11, Livewire 3, Pest (MySQL test DB: `docker run -d --name emotions-test-mysql -p 127.0.0.1:3307:3306 -e MYSQL_ROOT_PASSWORD=secret -e MYSQL_DATABASE=emotion_test mysql:8`), phpstan level 6, pint.

**Spec:** `docs/superpowers/specs/2026-11-18-approved-only-scores-design.md`

## Global Constraints

- Hospital users' behaviour is unchanged (approved only, partial rule).
- No data is altered on rejection; a rejected survey can be re-approved and counts again.
- Rejected and pending rows keep appearing in staff lists and on the evaluation page.
- `vendor/bin/phpstan analyse` and `vendor/bin/pint --test` clean before each commit.

## Review Focus

1. Staff open a hospital dashboard where every survey is pending: page renders the empty state, no division by zero. (Task 1 test `staff dashboard with only pending surveys renders empty`)
2. Heat-map drill-down rows for staff exclude rejected surveys, not just the cell average. (Task 1 test `heatmap cell rows exclude rejected surveys for staff`)
3. Re-approving a rejected survey returns it to every number. (Task 1 test `re-approving a rejected survey counts it again`)
4. A staff member's partial-response survey on a form that excludes partials stays out of staff numbers. (Task 1 test `partial rule applies to staff numbers`)
5. The hospital user CSV header is byte-identical to today (no Status column leaks). (Task 3 test)

---

### Task 1: Aggregates count approved evaluations only, for every viewer

**Files:**
- Modify: `app/Domain/Dashboard/DashboardQuery.php:31-50` (`base()`)
- Test: `tests/Feature/Dashboard/ApprovedOnlyScoresTest.php` (create)

**Interfaces:**
- Consumes: `Submission::scopeHospitalVisible(Builder): Builder` (app/Models/Submission.php:158), `Submission::factory()->pendingReview()` / `->rejected()` states, Pest helpers `superAdmin()`, `viewer(Hospital)`.
- Produces: `DashboardQuery::base()` now applies `hospitalVisible()` unconditionally. No signature change.

- [ ] **Step 1: Write the failing tests**

File `tests/Feature/Dashboard/ApprovedOnlyScoresTest.php`. `beforeEach`: freeze time at `2026-06-15 12:00:00` (both Carbon classes), create a hospital, a form with a `FormVersion` (factory) as `current_version_id`, a section via `Section` factory if one exists else through `FormVersioner::createInitialVersion($form, fixture('form_rating_yesno.json'))` as `DashboardQueryTest` does, and a helper `$this->make(string $status, float $pct, array $extra = [])` that creates a submission with `submitted_at => now()->subDays(3)`, `overall_pct => $pct`, `rag => Rag::fromPct($pct, 80, 60)`, `info => ['facility' => 'Main']`, a `SubmissionSectionScore` with `pct => $pct`, and the given status via `forceFill(['status' => $status])`.

Dataset: approved 80, approved 60, rejected 20, pending 100.

```php
test('staff headline and per-hospital numbers match the hospital view and skip rejected and pending', function () {
    $filters = DashboardFilters::fromRequest('30d');
    $staff = (new DashboardQuery(superAdmin()))->headline($this->hospital, $filters);
    $client = (new DashboardQuery(viewer($this->hospital)))->headline($this->hospital, $filters);

    expect($staff['avg'])->toBe(70.0)->and($staff['count'])->toBe(2)->and($staff)->toBe($client);
    expect((new DashboardQuery(superAdmin()))->perHospital($filters)[$this->hospital->id]['avg'])->toBe(70.0);
    expect((new DashboardQuery(superAdmin()))->distribution($this->hospital, $filters))->toBe(['green' => 1, 'amber' => 1, 'red' => 0, 'unscored' => 0]);
});

test('heatmap cell rows exclude rejected surveys for staff', function () {
    $cell = (new DashboardQuery(superAdmin()))->heatmapCell($this->hospital, DashboardFilters::fromRequest('30d'), null, 'Main', $this->form->title);
    expect($cell['count'])->toBe(2)->and(collect($cell['rows'])->pluck('pct')->all())->toBe([60.0, 80.0]);
});

test('re-approving a rejected survey counts it again', function () {
    $this->rejected->forceFill(['status' => SubmissionStatus::Approved])->save();
    expect((new DashboardQuery(superAdmin()))->headline($this->hospital, DashboardFilters::fromRequest('30d'))['count'])->toBe(3);
});

test('partial rule applies to staff numbers', function () {
    $this->make(SubmissionStatus::Approved->value, 40, ['is_partial' => true]);
    expect((new DashboardQuery(superAdmin()))->headline($this->hospital, DashboardFilters::fromRequest('30d'))['count'])->toBe(2);
});

test('staff dashboard with only pending surveys renders empty', function () {
    Submission::query()->whereIn('status', ['approved', 'rejected'])->delete();
    $this->actingAs(superAdmin())->get(route('admin.hospitals.dashboard', $this->hospital))->assertOk();
});
```

Check the actual route name for the staff view of a hospital dashboard in `routes/web.php` (grep `Hospital\Dashboard::class`) and use it.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Dashboard/ApprovedOnlyScoresTest.php`
Expected: the first four FAIL on counts/averages (staff sees 4 rows: avg 65, count 4); the fifth may already pass.

- [ ] **Step 3: Implement**

In `DashboardQuery::base()`, replace `Submission::query()->viewableBy($this->viewer)` with `Submission::query()->visibleTo($this->viewer)->hospitalVisible()`. Update the class docblock: "Scores and counts only ever include approved evaluations (plus the partial-response rule), for every viewer, so staff and hospital figures always match."

- [ ] **Step 4: Run the tests to verify they pass, then the whole suite**

Run: `vendor/bin/pest tests/Feature/Dashboard/ApprovedOnlyScoresTest.php` then `vendor/bin/pest`
Expected: all PASS. If an existing test relied on staff seeing pending rows in aggregates (grep `pendingReview` in `tests/Feature/Dashboard` and `tests/Feature/Admin/OverviewTest.php`), fix the expectation, not the code, and note it in the commit message.

- [ ] **Step 5: phpstan + pint, commit**

```bash
vendor/bin/phpstan analyse && vendor/bin/pint
git add app/Domain/Dashboard/DashboardQuery.php tests/Feature/Dashboard/ApprovedOnlyScoresTest.php
git commit -m "scores: every aggregate counts approved evaluations only, for every viewer"
```

### Task 2: "Rejected surveys" KPI on the admin overview

**Files:**
- Modify: `app/Livewire/Admin/Overview.php:40-70`
- Modify: `resources/views/livewire/admin/overview.blade.php:12-17`
- Test: `tests/Feature/Admin/OverviewTest.php`

**Interfaces:**
- Produces: view data `kpis['rejected']` (int, all time) and `kpis['rejectedRecent']` (int, last 30 days).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Admin/OverviewTest.php`:

```php
test('overview counts rejected surveys without adding them to any score', function () {
    $hospital = Hospital::factory()->create();
    $form = Form::factory()->for($hospital)->create();
    $version = FormVersion::factory()->create(['form_id' => $form->id]);
    $base = ['form_id' => $form->id, 'form_version_id' => $version->id, 'hospital_id' => $hospital->id];
    Submission::factory()->rejected()->create($base + ['submitted_at' => now()->subDays(2)]);
    Submission::factory()->rejected()->create($base + ['submitted_at' => now()->subDays(45)]);

    Livewire::actingAs(superAdmin())
        ->test(Overview::class)
        ->assertSee('Rejected surveys')
        ->assertSeeInOrder(['Rejected surveys', '2'])
        ->assertSee('1 in the last 30 days');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/pest tests/Feature/Admin/OverviewTest.php --filter rejected`
Expected: FAIL, "Rejected surveys" not found.

- [ ] **Step 3: Implement**

In `Overview::render()`, add `$rejected = Submission::query()->where('status', SubmissionStatus::Rejected->value)` and put `'rejected' => (clone $rejected)->count()`, `'rejectedRecent' => (clone $rejected)->where('submitted_at', '>=', now()->subDays(30))->count()` into `kpis`. In the blade KPI grid add, after "Awaiting review":

```blade
<x-ui.kpi label="Rejected surveys" :value="$kpis['rejected']" :note="$kpis['rejectedRecent'].' in the last 30 days · never counted in scores'" />
```

No href: there is no cross-hospital evaluations page; each hospital's evaluations list already filters by status.

- [ ] **Step 4: Run tests**

Run: `vendor/bin/pest tests/Feature/Admin/OverviewTest.php`
Expected: PASS.

- [ ] **Step 5: phpstan + pint, commit**

```bash
vendor/bin/phpstan analyse && vendor/bin/pint
git add app/Livewire/Admin/Overview.php resources/views/livewire/admin/overview.blade.php tests/Feature/Admin/OverviewTest.php
git commit -m "overview: rejected surveys KPI"
```

### Task 3: Status column in the staff CSV export

**Files:**
- Modify: `app/Domain/Dashboard/EvaluationCsvExporter.php:19-58`
- Modify: `app/Http/Controllers/EvaluationExportController.php:58-62`
- Test: `tests/Feature/Dashboard/ScreensTest.php` (append)

**Interfaces:**
- Produces: `EvaluationCsvExporter::rows(Builder $query, ?Form $singleForm, bool $withStatus = false): \Generator`.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Dashboard/ScreensTest.php`, reusing its `beforeEach` fixtures (check what `$this->hospital`, `$this->form`, `$this->viewer` are there):

```php
test('staff export has a Status column and the hospital export does not', function () {
    Submission::factory()->rejected()->create(['form_id' => $this->form->id, 'form_version_id' => $this->form->current_version_id, 'hospital_id' => $this->hospital->id, 'submitted_at' => now()->subDay()]);

    $staff = $this->actingAs(superAdmin())->get(route('evaluations.export', ['hospital' => $this->hospital->uuid, 'status' => 'rejected']))->streamedContent();
    $lines = array_map('str_getcsv', array_filter(explode("\n", trim($staff))));
    expect($lines[0])->toContain('Status')
        ->and(array_search('Status', $lines[0], true))->toBe(array_search('RAG', $lines[0], true) + 1)
        ->and(count($lines))->toBe(2)
        ->and($lines[1][array_search('Status', $lines[0], true)])->toBe('Rejected');

    $client = $this->actingAs($this->viewer)->get(route('evaluations.export'))->streamedContent();
    expect(str_getcsv(strtok($client, "\n")))->not->toContain('Status');
});
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/pest tests/Feature/Dashboard/ScreensTest.php --filter "Status column"`
Expected: FAIL, header does not contain 'Status'.

- [ ] **Step 3: Implement**

Add the `bool $withStatus = false` parameter; when true, insert `'Status'` into `$header` right after `'RAG'` and `$submission->status->label()` into `$row` after the RAG value. In the controller pass `$user->isEmotionStaff()` as the third argument.

- [ ] **Step 4: Run tests**

Run: `vendor/bin/pest tests/Feature/Dashboard/ScreensTest.php tests/Feature/Tenancy/IsolationTest.php`
Expected: PASS.

- [ ] **Step 5: phpstan + pint, commit**

```bash
vendor/bin/phpstan analyse && vendor/bin/pint
git add app/Domain/Dashboard/EvaluationCsvExporter.php app/Http/Controllers/EvaluationExportController.php tests/Feature/Dashboard/ScreensTest.php
git commit -m "export: Status column for eMotion staff"
```

### Task 4: Runbook note and full verification

**Files:**
- Modify: `docs/RUNBOOK.md` (append a short section "Rejected surveys and scores")

- [ ] **Step 1: Add the section**

Three lines: rejected and pending-review surveys never enter any score or count for any viewer; staff find them in a hospital's Evaluations list via the Status filter and in the staff CSV Status column; re-approving puts a survey back into the numbers.

- [ ] **Step 2: Full suite, phpstan, pint**

Run: `vendor/bin/pest && vendor/bin/phpstan analyse && vendor/bin/pint --test`
Expected: all green.

- [ ] **Step 3: Commit**

```bash
git add docs/RUNBOOK.md
git commit -m "docs: rejected surveys and scores"
```
