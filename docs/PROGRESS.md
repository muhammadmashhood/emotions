# Progress

Milestone checklist from SPEC.md section 15. Each entry lists what was built, how to verify it, and known gaps.

- [x] **M0 — Foundations**
- [x] **M1 — Tenancy, users, roles**
- [x] **M2 — Typeform connection and client**
- [x] **M3 — Linking, structure parsing, versions, mapping editor**
- [x] **M4 — Ingestion and scoring**
- [x] **M5 — Hospital dashboard and evaluations**
- [x] **M6 — Admin overview and operations**
- [x] **M7 — Phase 3 features**
- [x] **M8 — Hardening and handover**

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

## M4 — Ingestion and scoring

**Built:** `WebhookSignature`, `AnswerNormalizer`, `ScoringEngine` (+ `ScoringMapping`, `ScoreResult`) as pure classes. `TypeformWebhookController` on `POST /webhooks/typeform/{uuid}` (registered outside the web group: no session, no CSRF; 120/min per IP; raw body stored; 404 unknown uuid, 401 bad signature, 200 + job). `ProcessTypeformResponse` (queue `webhooks`, 3 tries, backoff 10/60/300 s). `SubmissionIngestor` (cache lock per form+token, duplicate / partial→complete rules, unknown field → `refreshFromTypeform` throttled to once per 5 min, info extraction incl. hidden fields, `require_review` status), `SubmissionScorer`, `BranchResolver` (settings-driven, used by M7), `Rescorer` + `RescoreFormSubmissions` + `php artisan evaluations:rescore {uuid} [--latest] [--on-version=N]`, `ResponseSyncer` (backfill with `before` pagination, reconcile since `last_synced_at − 24 h`), jobs `BackfillFormResponses`, `ReconcileFormResponses`, `CheckFormWebhook`, `CheckFormStructure`, commands `forms:health {webhooks|structure|reconcile}` and `deliveries:prune`, scheduled in `routes/console.php`. Admin "Deliveries" screen with payload view and retry.

**Verify:** `./vendor/bin/pest tests/Feature/Ingestion` — includes the end-to-end test (link → signed webhook → scored evaluation: Phone booking 85 %, Arrival 75 %, Waiting 60 %, Nursing 92 %, Doctor 80 %, Pharmacy 77.5 %, Facility 60 %, Overall 91.67 %, overall 77.65 % amber).

**Known gaps:** none.

## M5 — Hospital dashboard and evaluations

**Built:** `DashboardFilters` (presets 30d / 90d / 12m / custom, previous equal period, week/month bucket) and `DashboardQuery` (headline + delta, trend with one line per form and "Other", section averages, weakest questions, yes/no compliance, recent, distribution, per-hospital summary), always through `Submission::viewableBy()`. `EvaluationListQuery` + `EvaluationCsvExporter` (streamed, UTF-8 BOM, section columns for a single form) + `EvaluationExportController`. `EvaluationFileController` proxies Typeform uploads with the connection token (inline for images). Screens: hospital dashboard (`/dashboard`, admin `/hospitals/{uuid}/dashboard` with banner), evaluations list (filters, search, sort, export), evaluation detail (journey strip, collapsible sections, answers with points, uploads, admin raw payload / re-score / approve / reject), mini dashboard on the admin hospital page. Blade components: filters bar, journey strip, chart (Chart.js), rag pill. `DemoSeeder` + `DemoResponseFactory`: 2 hospitals × 2 forms, 300 realistic evaluations over 12 months (`DEMO_EVALUATIONS` to change).

**Verify:** `./vendor/bin/pest tests/Feature/Dashboard tests/Feature/Tenancy`; `php artisan migrate:fresh --seed` then log in as `viewer@ittefaq.example.com` / `password`.

**Known gaps:** PDF export and review queue come in M7.

## M6 — Admin overview and operations

**Built:** Admin overview (`/admin`): per-hospital table (active forms, evaluations in period, average score, trend arrow vs previous period, forms needing mapping review, webhook problems, link to dashboard) with a 30d/90d/12m switch; alerts panel (missing Typeform connection or connection error, forms with `webhook_status != active` with an inline "Repair webhook" button, forms with `needs_review`, failed deliveries in the last 24 h, count of rejected requests). Audit log screen (`/admin/audit-log`) with action / hospital / user / date filters and expandable change details. Webhook repair, re-sync and the all-users screen were delivered in M3 / M1 and are linked from here.

**Verify:** `./vendor/bin/pest tests/Feature/Admin/OverviewTest.php` — includes "a deleted webhook shows an alert and Repair webhook fixes it" against a faked API.

**Known gaps:** none.

## M7 — Phase 3 features

**Built:** Review workflow: hospitals with `require_review` queue new evaluations as `pending_review`; `/admin/review` lists them (hospital filter, note, approve / reject, audited); the same decisions are available on the evaluation detail page; hospital users only ever see approved evaluations. Branches: form settings offer a branch source (hidden field key or a survey question); `BranchResolver` creates branches on first sight and assigns `branch_id` on ingest and on re-score; branch filter on dashboard and list, branch column in list and CSV. PDF export: `GET /evaluations/{uuid}/pdf` renders the detail layout (header, journey strip, sections, answers) with dompdf, policy-checked. Workspace auto-linking: hospital settings map a Typeform workspace; `php artisan forms:auto-link` (hourly) links new forms in mapped workspaces with `needs_review = true`, audited as automatic (`linked_by` null).

**Verify:** `./vendor/bin/pest tests/Feature/Phase3`.

**Known gaps:** PDFs list attachment file names rather than embedding images (the files require the Typeform token and are served through the authenticated proxy instead).

## M8 — Hardening and handover

**Built:** `SecurityHeaders` middleware (X-Frame-Options DENY, nosniff, Referrer-Policy, Permissions-Policy, HSTS on HTTPS, CSP compatible with Livewire/Alpine/Vite, Vite dev origins only in `local`); exceptions reported without request bodies; styled error pages (401/403/404/419/429/500/503); `php artisan app:seed-demo --large` (≈50 000 evaluations, bulk path through the pure scoring engine); performance pass with a covering index on `answers` and query rewrites; complete `README.md` (setup, tests, ngrok, linking, scoring, production deployment incl. worker, cron, HTTPS, backups, `APP_KEY` care) and `docs/RUNBOOK.md`.

**Performance (50 000 evaluations, 3.95 M answers, hospital with 25 000 evaluations, one form with 5 514 evaluations in the 90-day window; local MySQL 8 in Docker):**

| Query | Before | After |
|---|---|---|
| headline | 67 ms | 22 ms |
| trend (all forms / 12 months) | 20 / 107 ms | 16 / 59 ms |
| section averages | 67 ms | 52 ms |
| weakest questions | 489 ms | 257 ms |
| yes/no compliance | 575 ms | 103 ms |
| recent + distribution | 23 ms | 14 ms |
| admin per-hospital summary | 134 ms | 123 ms |
| **full dashboard** | **1 483 ms** | **645 ms** |

`EXPLAIN` confirms range scans on `(form_id, submitted_at)` / `(hospital_id, status, submitted_at)` and an index-only (`Using index`) join on `answers_scoring_covering_index`.

**Verify:** `./vendor/bin/pest tests/Feature/SecurityHeadersTest.php`; `php artisan app:seed-demo --large` then time the dashboard; `curl -I http://localhost:8080/login` shows the headers.

**Known gaps:** coverage is reported in CI only (no xdebug/pcov on the build machine); the manual Typeform round-trip (real token, real form, real submission) needs a public HTTPS URL and a Typeform account and was verified only through the faked-API end-to-end test.

## Post-M8 — Dashboard redesign

**Built (owner request):** sidebar shell with role-based navigation, "Patient Experience — Mystery Shopping Dashboard" with KPI tiles (overall score + delta, evaluations, likelihood to recommend from NPS questions, critical items passed, facilities evaluated), trend line (one series per survey), outcome donut, score by section/survey bars, facility ranking (top/bottom), touchpoint heat-map (facilities × sections or surveys), weakest questions, yes/no compliance, live feed. Evaluation page with score ring hero, info grid, gradient journey tiles and section cards with per-answer point pills. New `DashboardQuery::kpis()`, `facilityRanking()`, `heatmap()` with tests. Screenshots in `docs/screenshots/`.
