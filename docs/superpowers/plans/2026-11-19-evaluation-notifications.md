# Evaluation Notifications Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Email staff when an evaluation arrives and hospital admins when one is published.

**Architecture:** Two queued Mailables, one notifier service that picks recipients and swallows mail errors, three call sites (webhook job, review queue approve, evaluation page approve).

**Tech Stack:** Laravel 11 markdown mail, database queue, Pest with `Mail::fake()` (MySQL test DB via `docker run -d --name emotions-test-mysql -p 127.0.0.1:3307:3306 -e MYSQL_ROOT_PASSWORD=secret -e MYSQL_DATABASE=emotion_test mysql:8`), phpstan level 6, pint.

**Spec:** `docs/superpowers/specs/2026-11-19-evaluation-notifications-design.md`

## Global Constraints

- Never notify for `SubmissionSource::Backfill` / `Reconcile`, partial responses, rejections, edits or re-scores.
- One Mailable per recipient. Mail failures are logged (`evaluation.notify_failed`) and never thrown.
- phpstan + pint clean before each commit.

## Review Focus

1. A hospital admin of another hospital never receives a published email. (Task 1 test)
2. Deactivated users and users of a deactivated hospital are skipped. (Task 1 test)
3. A partial response that later completes notifies exactly once, on completion. (Task 2 test)
4. A hospital without review gets both emails from one webhook. (Task 2 test)
5. Re-approving a rejected evaluation sends published again. (Task 3 test)

---

### Task 1: Mailables and notifier

**Files:**
- Create: `app/Mail/EvaluationSubmittedMail.php`, `app/Mail/EvaluationPublishedMail.php`, `resources/views/emails/evaluation-submitted.blade.php`, `resources/views/emails/evaluation-published.blade.php`, `app/Domain/Submissions/EvaluationNotifier.php`
- Test: `tests/Feature/Notifications/EvaluationNotifierTest.php`

**Interfaces:**
- Produces: `EvaluationNotifier::submitted(Submission $submission): void`, `EvaluationNotifier::published(Submission $submission): void`; Mailables take `(Submission $submission)` and implement `ShouldQueue`; public `$submission` loaded with `hospital`, `form`, `branch`.

- [ ] **Step 1: Failing tests** (fixtures: hospital with form + version; submission via factory `withScore(82.5)` and `info => ['facility' => 'Main', 'visit_date' => '2026-11-10']`; users: 2 super admins (one inactive), 1 QA, 1 hospital admin of the hospital, 1 viewer of the hospital, 1 hospital admin of another hospital, 1 hospital admin of an inactive hospital):

```php
test('submitted goes to active staff only, one mail each', function () {
    Mail::fake();
    app(EvaluationNotifier::class)->submitted($this->submission);
    Mail::assertQueued(EvaluationSubmittedMail::class, 2);
    Mail::assertQueued(EvaluationSubmittedMail::class, fn ($m) => $m->hasTo($this->admin->email));
    Mail::assertQueued(EvaluationSubmittedMail::class, fn ($m) => $m->hasTo($this->qa->email));
    Mail::assertNotQueued(EvaluationSubmittedMail::class, fn ($m) => $m->hasTo($this->inactiveAdmin->email));
    Mail::assertNotQueued(EvaluationPublishedMail::class);
});
test('published goes to the hospital\'s active admins only', function () {
    Mail::fake();
    app(EvaluationNotifier::class)->published($this->submission);
    Mail::assertQueued(EvaluationPublishedMail::class, 1);
    Mail::assertQueued(EvaluationPublishedMail::class, fn ($m) => $m->hasTo($this->hospitalAdmin->email));
    Mail::assertNotQueued(EvaluationPublishedMail::class, fn ($m) => $m->hasTo($this->viewer->email) || $m->hasTo($this->otherAdmin->email) || $m->hasTo($this->inactiveHospitalAdmin->email));
});
test('emails carry the summary and a link', function () {
    $html = (new EvaluationSubmittedMail($this->submission))->render();
    expect($html)->toContain($this->hospital->name, $this->form->title, 'Main', '82.5', route('evaluations.show', $this->submission));
    expect((new EvaluationSubmittedMail($this->submission))->envelope()->subject)->toBe('New evaluation: '.$this->hospital->name.' · '.$this->form->title);
    expect((new EvaluationPublishedMail($this->submission))->envelope()->subject)->toBe('Evaluation published: '.$this->form->title);
});
test('a mail failure is logged, not thrown', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp down'));
    Log::shouldReceive('warning')->once()->withArgs(fn ($msg) => $msg === 'evaluation.notify_failed');
    app(EvaluationNotifier::class)->submitted($this->submission);
})->expectNotToPerformAssertions();
```

- [ ] **Step 2: Run** `vendor/bin/pest tests/Feature/Notifications` — Expected: FAIL (classes missing).
- [ ] **Step 3: Implement.** Mailables: `use Queueable, SerializesModels`, `implements ShouldQueue`, `envelope()` subjects as above, `content()` markdown views. Views mirror `emails/user-invitation.blade.php`: heading, a short table-like list (Hospital, Survey, Facility, Visit date, Score), `<x-mail::button :url="route('evaluations.show', $submission)">View evaluation</x-mail::button>`. Notifier: `recipientsForSubmitted()` = `User::query()->whereIn('role', staff roles)->where('is_active', true)`; `recipientsForPublished()` = hospital_admin, hospital_id, is_active, `whereHas('hospital', is_active)`; loop `Mail::to($user)->queue(new ...)` inside try/catch Throwable → `Log::warning('evaluation.notify_failed', [...])`. Load `hospital`, `form`, `branch` with `loadMissing` first.
- [ ] **Step 4: Run** tests → PASS; phpstan + pint. **Step 5: Commit** `notify: evaluation submitted / published emails and notifier`.

### Task 2: Webhook call site

**Files:**
- Modify: `app/Jobs/ProcessTypeformResponse.php:52-57`
- Test: `tests/Feature/Notifications/EvaluationNotificationFlowTest.php`

**Interfaces:** consumes `EvaluationNotifier` from Task 1; `IngestOutcome::$status` (`created`/`updated`/`duplicate`), `Submission::$is_partial`, `$status`.

- [ ] **Step 1: Failing tests** using the `deliver()` helper pattern from `tests/Feature/Ingestion/IngestionTest.php` (copy the helper locally as `deliverWebhook`) and `fixture webhook_payload_rACXmOJ2.json`:

```php
test('a live complete response emails staff, and hospital admins when the hospital has no review', function () {
    Mail::fake(); // hospital require_review=false
    deliverWebhook($this, $this->form, $this->raw);
    Mail::assertQueued(EvaluationSubmittedMail::class, 1);
    Mail::assertQueued(EvaluationPublishedMail::class, 1);
});
test('with review required only staff are emailed on arrival', ...assertQueued Submitted 1, assertNotQueued Published);
test('duplicates, partials and backfills do not email', function () {
    // same payload twice → still 1 submitted; a payload with event_type form_response_partial → 0; app(ResponseSyncer) backfill path → 0
});
test('a partial that later completes emails once, on completion', ...);
```

- [ ] **Step 2: Run** → FAIL. **Step 3: Implement**: after the successful ingest in the job, `if (! $outcome->isDuplicate() && $outcome->submission !== null && ! $outcome->submission->is_partial) { $notifier->submitted($s); if ($s->status === SubmissionStatus::Approved) { $notifier->published($s); } }` (inject `EvaluationNotifier` into `handle`). **Step 4:** tests + `tests/Feature/Ingestion` PASS; phpstan + pint. **Step 5: Commit** `notify: email on live webhook arrivals`.

### Task 3: Approve call sites

**Files:**
- Modify: `app/Livewire/Admin/Review.php:47-66`, `app/Livewire/Hospital/Evaluations/Show.php:246-262`
- Test: append to `tests/Feature/Notifications/EvaluationNotificationFlowTest.php`

- [ ] **Step 1: Failing tests**: approving from `Review` queues one `EvaluationPublishedMail` to the hospital admin; rejecting queues none; approving from `Show` queues one; rejecting then approving again on `Show` queues one more.
- [ ] **Step 2: Run** → FAIL. **Step 3: Implement**: inject `EvaluationNotifier` into `approve()`/`reject()` → `decide(..., ?EvaluationNotifier)`; simpler: resolve `app(EvaluationNotifier::class)` inside `decide`/`review` and call `published()` when `$status === SubmissionStatus::Approved`, after the audit log. **Step 4:** tests + `tests/Feature/Phase3/ReviewWorkflowTest.php` PASS; phpstan + pint. **Step 5: Commit** `notify: email hospital admins when staff approve`.

### Task 4: Runbook + full verification

- [ ] Append to `docs/RUNBOOK.md` "Evaluation email notifications": who gets what, when nothing is sent, that mail is queued (check `jobs`/`failed_jobs` and `evaluation.notify_failed` in the log if emails are missing).
- [ ] `vendor/bin/pest && vendor/bin/phpstan analyse && vendor/bin/pint --test` green. Commit `docs: evaluation email notifications`.
