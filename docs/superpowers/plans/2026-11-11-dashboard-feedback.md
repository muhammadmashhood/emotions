# Dashboard Feedback (9 Nov 2026) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Resolve all ten items of the 9 Nov 2026 "MS Dashboard Feedback" memo (logos, delete hospital, QA role, invite crash, delete forms, interactive heat-map, visit report design, attachments in PDF, dashboard title, edit a submitted evaluation).

**Architecture:** Each item is one task with its own tests and commit. Attachments move from "streamed from Typeform on demand" to "downloaded once at ingest and stored on the private disk" (`submission_attachments` table), which the PDF, the edit feature and the file controller all build on. Edits are stored as per-field overrides on `submissions.edits` and applied inside the existing scoring pipeline so re-scoring keeps them. The QA role is a non-hospital role alongside super admin; `User::isEmotionStaff()` gates everything the memo says QA should reach.

**Tech Stack:** Laravel 11, Livewire 3, Pest, dompdf, MySQL (tests on 127.0.0.1:3307).

**Spec:** the memo "MS Dashboard Feedback - 9 Nov 2026" (PDF, not in repo). Items are referenced as #1 … #10.

## Global Constraints

- Production runs on SiteGround with the whole app inside `public_html`; `deploy/siteground/root.htaccess` is copied to the server's `public_html/.htaccess` on every deploy.
- Never store SMTP credentials in the repo; `.env` on the server holds them.
- `vendor/bin/pint`, `vendor/bin/phpstan` (level 6) and `vendor/bin/pest` must pass before every commit.
- Hospital users must never see admin-only tools; QA users must never reach hospital/user/form/settings management.

## Review Focus

1. A Typeform file download that fails (401, network) must not fail ingestion or the queue job; the answer still shows with the on-demand fallback. Test in Task 8.
2. Re-scoring a form (bulk) must preserve admin edits. Test in Task 10.
3. Deleting a hospital whose Typeform token is revoked must still delete (webhook unregister errors are logged, not fatal). Test in Task 5.
4. A QA user hitting `/admin/hospitals` or `/admin/users` gets 403, not a crash; hitting `/admin/review` works. Test in Task 4.
5. Heat-map cell with no data is not clickable and selecting a cell for a facility that disappears after a filter change does not error. Test in Task 7.

---

### Task 1: Hospital logos (#1)

**Files:** Modify `deploy/siteground/root.htaccess`.

- [ ] Add, before the directory deny rule, `RewriteRule ^storage/(.*)$ public/storage/$1 [L]` so `/storage/logos/x.png` resolves to the `storage:link` symlink inside `public/`.
- [ ] Remove `storage` from the hard-deny list (it is now forwarded; non-existent paths fall through to Laravel's 404).
- [ ] Commit: `deploy: serve /storage through public/storage so hospital logos load`.

### Task 2: Dashboard page title (#9)

**Files:** Modify `resources/views/livewire/hospital/dashboard.blade.php:14`, `tests/Feature/Dashboard/HospitalDesignTest.php:27`.

- [ ] Change the test assertion to `->assertSee($hospital->name.' Mystery Shopping Dashboard')`; run, expect FAIL.
- [ ] Set header title to `$hospital->name.' Mystery Shopping Dashboard'`; run, expect PASS. Commit.

### Task 3: Invitation mail failure (#4)

**Files:** Create `app/Domain/Users/InvitationMailFailed.php` (RuntimeException). Modify `app/Domain/Users/InvitationService.php` (`invite`, `resend`), `app/Livewire/Admin/Users/Index.php::invite`, `app/Livewire/Shared/UserManagement.php::invite`, `resend`. Test `tests/Feature/Admin/InvitationTest.php`.

- [ ] Test `an unreachable mail server shows a validation error instead of a 500`: `Mail::shouldReceive('to')->andThrow(new \Symfony\Component\Mailer\Exception\TransportException('getaddrinfo failed'))`; call `invite` on `UsersIndex`; `assertHasErrors(['inviteEmail'])`; invitation row deleted.
- [ ] `InvitationService::invite` wraps the send in try/catch of `TransportExceptionInterface`; on failure deletes the invitation row, logs `invitation.mail_failed`, throws `InvitationMailFailed` with message `The invitation could not be emailed. Check the MAIL_* settings on the server.`
- [ ] Both Livewire `invite` methods catch `InvitationMailFailed` and `addError('inviteEmail', $e->getMessage())`; `resend` methods likewise on `inviteEmail`/status flash. Commit.

### Task 4: eMotion QA role (#3)

**Files:** Modify `app/Enums/Role.php`, `app/Models/User.php`, `app/Policies/SubmissionPolicy.php`, `app/Policies/HospitalPolicy.php::view`, `routes/web.php`, `resources/views/livewire/layout/navigation.blade.php`, `resources/views/livewire/layout/partials/admin-sidebar.blade.php`, `resources/views/livewire/admin/users/index.blade.php`, `app/Livewire/Hospital/Evaluations/Show.php` (`isAdmin` → `isEmotionStaff`), `database/migrations/*_add_emotion_qa_role.php` (users.role and user_invitations.role enum columns, check their type first), `database/factories/UserFactory.php::qa()`. Test `tests/Feature/Admin/QaRoleTest.php`.

**Interfaces:** `Role::QualityAssurance = 'emotion_qa'`, label `eMotion QA`; `Role::isHospitalRole()` false for it; `Role::staffRoles(): list<Role>`; `User::isQa(): bool`; `User::isEmotionStaff(): bool` (super admin or QA).

- [ ] Tests: QA user can open `admin.review`, `admin.overview`, `hospitals.dashboard`, `hospitals.evaluations.index`, an evaluation page and approve it; gets 403 on `admin.hospitals.index`, `admin.users.index`, `admin.settings.typeform`; super admin can invite a QA (no hospital); the home route sends QA to `admin.overview`.
- [ ] Routes: `admin.overview`, `admin.review`, `hospitals.dashboard`, `hospitals.evaluations.index` move to middleware `role:super_admin,emotion_qa`.
- [ ] Sidebar: QA sees Overview, Review queue and the hospital switcher only; pill reads `QA`.
- [ ] Users page: QA radio in the invite modal; role labels use `Role::label()` (SuperAdmin label becomes `eMotion admin`). Commit.

### Task 5: Delete a hospital (#2)

**Files:** Create `app/Domain/Hospitals/HospitalDeleter.php`. Modify `app/Policies/HospitalPolicy.php::delete`, `app/Livewire/Admin/Hospitals/Show.php` (`confirmDeleteName`, `deleteHospital(HospitalDeleter)`), settings tab in `resources/views/livewire/admin/hospitals/show.blade.php`. Test `tests/Feature/Admin/HospitalDeletionTest.php`.

**Interfaces:** `HospitalDeleter::delete(Hospital $hospital, User $by): void` — unregisters each active form's webhook (TypeformException logged, not rethrown), deletes attachments files, submissions, forms, branches, users, invitations, logo file, the hospital; audit `hospital.deleted` with `['name' => …, 'forms' => n, 'submissions' => n, 'users' => n]`.

- [ ] Tests: deleting removes hospital, its forms, submissions, answers, users, invitations and calls webhook DELETE (Http::fake); wrong confirmation name → validation error and nothing deleted; webhook 401 still deletes; hospital admin cannot call it.
- [ ] UI: "Danger zone" card under Settings with text input `confirmDeleteName`, button disabled until it equals the name, `wire:confirm`. Redirect to `admin.hospitals.index` with flash `Hospital "{name}" deleted.` Commit.

### Task 6: Delete or unlink a form (#5)

**Files:** Modify `app/Domain/Forms/FormLinker.php::delete(Form, User)`, `app/Policies/FormPolicy.php::delete`, `app/Livewire/Admin/Hospitals/Show.php` (`unlinkForm(int)`, `deleteForm(int)`), forms tab row actions in `show.blade.php`, `app/Livewire/Admin/Forms/Show.php::delete`, `resources/views/livewire/admin/forms/show.blade.php` (Delete button). Test `tests/Feature/Forms/FormDeletionTest.php`.

- [ ] Tests: Unlink from the Forms tab calls webhook delete and marks Unlinked; Delete removes form, versions, submissions, answers, section scores and nulls `webhook_deliveries.form_id`; audit `form.deleted`.
- [ ] Row actions: `Unlink` (active forms) and `Delete` (all), both with `wire:confirm`. Commit.

### Task 7: Interactive heat-map (#6)

**Files:** Modify `app/Domain/Dashboard/DashboardQuery.php::heatmapCell`, `app/Livewire/Hospital/Dashboard.php` (`?string $cellFacility`, `?string $cellColumn`, `selectCell(string, string)`, `clearCell()`), `resources/views/livewire/hospital/dashboard.blade.php` heat-map block. Tests `tests/Feature/Dashboard/DashboardQueryTest.php`, `tests/Feature/Dashboard/HeatmapDrilldownTest.php`.

**Interfaces:** `heatmapCell(?Hospital, DashboardFilters, ?Form, string $facility, string $column, int $limit = 25): array{avg: float|null, count: int, rows: list<array{uuid: string, submitted_at: ?Carbon, title: string, doctor: ?string, pct: ?float, status: SubmissionStatus}>}` — with a form the column is a section name and `pct` the section pct; without, the column is a form title and `pct` the overall pct.

- [ ] Tests: cell returns only submissions of that facility+section ordered by pct asc; Livewire `selectCell` renders the list with a link to `evaluations.show`; selecting a cell with no data shows `No scored evaluations for this cell.`
- [ ] Blade: each cell with a value is a `<button wire:click="selectCell(...)">`; selected cell gets `ring-2 ring-em-accent`; a panel under the table lists the evaluations (date, doctor, score pill, "Open →") with a Close button. Commit.

### Task 8: Stored attachments (#8 part 1)

**Files:** Create migration `create_submission_attachments_table`, `app/Models/SubmissionAttachment.php`, `app/Domain/Submissions/AttachmentStore.php`, `app/Jobs/StoreSubmissionAttachments.php`, `app/Http/Controllers/EvaluationAttachmentController.php`, `database/factories/SubmissionAttachmentFactory.php`. Modify `app/Domain/Submissions/SubmissionIngestor.php` (dispatch job after ingest when any answer is `file_url`), `app/Http/Controllers/EvaluationFileController.php` (serve the stored copy first), `app/Models/Submission.php::attachments()`, `routes/web.php`. Tests `tests/Feature/Ingestion/AttachmentsTest.php`.

**Interfaces:** columns `submission_id, answer_typeform_field_id (nullable), source enum(typeform,upload), disk, path, original_name, mime, size, uploaded_by (nullable), timestamps`. `AttachmentStore::syncFromTypeform(Submission): int` (count stored; skips fields already stored; download failures logged). `AttachmentStore::upload(Submission, UploadedFile, User): SubmissionAttachment`. `AttachmentStore::delete(SubmissionAttachment, User): void`. `SubmissionAttachment::isImage(): bool`, `dataUri(): ?string` (base64 for PDF, images only, ≤ 4 MB).

- [ ] Tests: ingesting the rACXmOJ2 payload with `Http::fake` for the file URL stores one attachment per file answer on disk `local` under `attachments/{uuid}/`; a 401 from Typeform stores nothing and the job does not throw; file route serves the stored copy without calling Typeform; an upload by an admin creates a row with `source = upload`. Commit.

### Task 9: Visit report PDF — design and embedded attachments (#7, #8 part 2)

**Files:** Modify `resources/views/pdf/evaluation.blade.php`, `app/Http/Controllers/EvaluationPdfController.php` (load `attachments`). Test `tests/Feature/Phase3/PdfExportTest.php`.

- [ ] Test: with a stored image attachment the PDF HTML (render view directly) contains `data:image/png;base64`; non-image attachments are listed by name under "Supporting documents".
- [ ] Redesign: fixed-width meta grid, journey bars in equal columns, section cards with aligned three-column rows, "Attachments" rows show the image (max 60 mm wide) under the question, "Supporting documents" section at the end, footer unchanged. Commit.

### Task 10: Edit a submitted evaluation (#10)

**Files:** Migration `add_edits_to_submissions` (json nullable). Modify `app/Domain/Scoring/ScoringEngine.php::score(..., array $pointOverrides = [])`, `app/Domain/Submissions/SubmissionScorer.php` (applies `$submission->edits`), `app/Policies/SubmissionPolicy.php::edit`, `app/Livewire/Hospital/Evaluations/Show.php` (`editing`, `editPoints`, `editText`, `documents`, `startEditing`, `cancelEditing`, `saveEdits(Rescorer, AuditLogger)`, `uploadDocuments(AttachmentStore, AuditLogger)`, `deleteDocument(int, AttachmentStore, AuditLogger)`), `resources/views/livewire/hospital/evaluations/show.blade.php`. Tests `tests/Unit/Domain/ScoringEngineOverridesTest.php`, `tests/Feature/Phase3/EvaluationEditTest.php`.

**Interfaces:** `submissions.edits = {"<typeform_field_id>": {"points": float|null, "text": string|null, "original_points": float|null, "original_text": string|null, "edited_by": int, "edited_at": iso8601}}`. Overrides clamp to `[0, max_points]`; a text override replaces `value_text` only.

- [ ] Tests: engine override changes points and section pct but not max; `saveEdits` re-scores overall pct, stores originals, audits `submission.edited`; bulk `Rescorer::rescoreForm` keeps the override; hospital admin gets 403 on `saveEdits`; uploading two files lists them for hospital viewers.
- [ ] UI: "Edit evaluation" button in Admin tools toggles inline number inputs (scored questions) and textareas (comment fields); Save/Cancel; "edited" pill with original value in a tooltip; "Supporting documents" card with upload (multiple, ≤ 10 MB each) and delete for staff. Commit.

### Task 11: Docs and server notes

- [ ] `docs/RUNBOOK.md`: roles table (add QA), "Logos not loading", "Invitation 500", deleting hospitals/forms, editing evaluations.
- [ ] `deploy/siteground/env.example`: SiteGround SMTP example (`MAIL_HOST=<server>.siteground.biz`, port 465, ssl). Commit, push.
