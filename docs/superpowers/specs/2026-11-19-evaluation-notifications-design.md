# Evaluation email notifications design

**Date:** 2026-11-19

## Intent

Staff want to know the moment a mystery-shopper evaluation comes in; hospital admins want to know when an evaluation is approved and visible on their dashboard.

## Goals

- "New evaluation" email to every active Super Admin and QA user when a complete response arrives through the live Typeform webhook.
- "Evaluation published" email to every active Hospital Admin of the hospital when an evaluation becomes approved (on arrival for hospitals without review, or when staff approve).
- One email per evaluation per recipient. Queued. A mail failure never affects the evaluation.

## Non-goals

- Digests (user chose per-evaluation).
- Notifications for backfill, reconciliation, partial responses, rejections, edits or re-scoring.
- Per-user notification preferences.

## Anti-goals

- Flooding staff during a form backfill.
- Exposing hospital addresses to each other (one email per recipient).

## Approaches considered

1. **One notifier service called at the two moments (chosen).** `EvaluationNotifier::submitted()` / `::published()` called from the webhook job and the two Approve actions.
2. Laravel events + listeners. More indirection for no benefit today.
3. Scheduled scan. A digest in disguise.

## Design

- `App\Domain\Submissions\EvaluationNotifier` with `submitted(Submission $s): void` and `published(Submission $s): void`. Each resolves recipients (active users, and for published also an active hospital), queues one Mailable per recipient, and catches/logs any throwable.
- Mailables `App\Mail\EvaluationSubmittedMail` and `App\Mail\EvaluationPublishedMail` (ShouldQueue), markdown views `emails.evaluation-submitted` / `emails.evaluation-published`, showing hospital, survey, facility, visit date, overall score (or "Not scored") and a button to `evaluations.show`.
- Call sites: `ProcessTypeformResponse` after a successful ingest when the outcome is created or updated and the submission is not partial: `submitted()`, then `published()` if status is Approved. `Admin\Review::decide` and `Hospital\Evaluations\Show::review`: `published()` when the new status is Approved.
- Recipients: submitted → `User` where role in staff roles and is_active; published → `User` where role hospital_admin, hospital_id = submission hospital, is_active, and the hospital is active.

## Builder's calls

- Subject lines: "New evaluation: {hospital} · {survey}" and "Evaluation published: {survey}".
- Sender stays MAIL_FROM.
