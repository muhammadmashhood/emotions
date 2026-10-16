# Progress

Milestone checklist from SPEC.md section 15. Each entry lists what was built, how to verify it, and known gaps.

- [x] **M0 — Foundations**
- [x] **M1 — Tenancy, users, roles**
- [x] **M2 — Typeform connection and client**
- [x] **M3 — Linking, structure parsing, versions, mapping editor**
- [ ] **M4 — Ingestion and scoring**
- [ ] **M5 — Hospital dashboard and evaluations**
- [ ] **M6 — Admin overview and operations**
- [ ] **M7 — Phase 3 features**
- [ ] **M8 — Hardening and handover**

## M0 — Foundations

**Built:** Laravel 11 (v11.57) with Breeze Livewire/Volt stack (registration and email verification removed, invite-only), Tailwind with the design tokens from spec section 11 and Public Sans, Chart.js + SortableJS via npm, Pest 3 (+ Livewire plugin), Larastan level 6, Pint, Docker Compose (app, nginx, mysql, redis, worker, scheduler, mailpit), GitHub Actions CI, `.env.example` per section 13.

**Verify:**
```
docker run -d --name emotion-test-mysql -e MYSQL_ROOT_PASSWORD=secret -e MYSQL_DATABASE=emotion_test -p 127.0.0.1:3307:3306 mysql:8.0
./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
docker compose up -d --build   # then open http://localhost:8080/login
```

**Known gaps:** none.

## M1 — Tenancy, users, roles

**Built:** All migrations from spec section 4 (hospitals, users with roles, branches, typeform_connections, forms, form_versions, sections, form_fields, webhook_deliveries, submissions, answers, submission_section_scores, audit_logs, user_invitations) with models, casts (`FormSettings` DTO, encrypted secrets) and factories. `BelongsToHospital` trait with `visibleTo()`; `Submission::resolveRouteBinding` applies the visibility scope so cross-tenant lookups are 404. Policies for Hospital, User, Form, Submission. `role:` and `active` middleware (inactive users / hospitals are logged out and cannot log in). `AuditLogger`, login/failed-login audit listener, `InvitationService` (hashed token, 72 h expiry, mail), `UserManager`. Screens: admin overview (stub), hospitals list/create/show (tabs), all-users screen, hospital_admin "Team" page, invitation acceptance page. `app:create-super-admin` command and `SuperAdminSeeder` (env, local only), demo seeder with Ittefaq Hospital and CMH.

**Verify:** `./vendor/bin/pest --filter "Tenancy|Invitation|HospitalManagement|Smoke"`; or log in as `admin@example.com` after `migrate --seed`, create a hospital, invite a user, open the link from Mailpit.

**Known gaps:** admin overview is a stub until M6; dashboard is an empty state until M5.

## M2 — Typeform connection and client

**Built:** `TypeformClient` (me, workspaces, forms list, form, put/get/delete webhook, responses, file download) with 5 s / 20 s timeouts, retries on 429/5xx/connection errors (1 s, 2 s, 4 s, `Retry-After` honoured), per-connection throttle via `RateLimiter`, typed exceptions, token-free logging. DTOs in `Domain/Typeform/Data`. `ConnectionManager` (verify with `/me`, encrypted save, test), `FormCatalog` (cached per-workspace listing, excludes forms linked elsewhere). Screens: Settings → Typeform, hospital form picker (search, workspace filter, HTTPS warning).

**Verify:** `./vendor/bin/pest tests/Unit/Typeform tests/Feature/Admin/TypeformConnectionTest.php`.

**Known gaps:** the picker's "Link form" action is wired in M3.

## M3 — Linking, structure parsing, versions, mapping editor

**Built:** Synthetic fixtures mirroring the real form (`tests/Fixtures/typeform/`, regenerate with `php tests/Fixtures/typeform/generate.php`; `docs/fixtures/*` is used automatically when present). `FormStructureParser` (statements/groups → sections, per-type defaults, yes/no choice detection, info keys, title cleaning, structure hash). `FormVersioner` (initial version, carry-over of names/weights/moves/scored/info keys, admin-created sections survive, new fields flagged, `refreshFromTypeform` creates a version only when the hash changes). `WebhookRegistrar` (register / check+repair / unregister), `FormLinker` (link, relink, unlink, cross-hospital guard), `MappingEditor` (validated persistence + audit diff). Screens: form picker "Link form" action, form detail (scoring settings, webhook status + repair, versions, re-sync, recalculate, unlink), mapping editor (rename, weight, reorder, add/delete sections, scored toggle, info key, "Move to…", SortableJS drag across panels, New badges, version selector, read-only older versions, rescore prompt). Job shells `BackfillFormResponses` and `RescoreFormSubmissions` (bodies in M4).

**Verify:** `./vendor/bin/pest tests/Unit/Domain tests/Feature/Forms`. In the UI: link a form, open Mapping, drag "The website/ App was easy to access" into "Website/ App booking", save.

**Known gaps:** jobs are dispatched but do nothing until M4.
