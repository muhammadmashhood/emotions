# eMotion Evaluations Platform — Build Specification

> **For Claude Code.** This document is the single source of truth for building this project end to end.
> Read it fully before writing code. Work through the milestones in section 15 in order. Each milestone
> has acceptance criteria and required tests. A milestone is done only when its tests pass, the full
> test suite is green, and static analysis is clean. Section 18 has your working rules.

---

## 1. What we are building

A multi-tenant web platform that turns **Typeform mystery-shopping surveys** into **scored dashboards** for hospitals.

- The **admin** (our company) designs survey forms in Typeform, one or more per hospital.
- In this platform, the admin creates a **hospital account** (e.g. *Ittefaq Hospital*, *CMH*) and **links** Typeform forms to it.
- Linking a form makes the platform automatically:
  1. read the form structure from the Typeform API,
  2. map every question to a **section** (based on the form's statement blocks),
  3. register a **webhook** on that form via the Typeform API (nobody copies URLs by hand),
  4. backfill responses that already exist.
- Every new submission arrives by webhook, is verified, stored, **scored per section**, and appears on that hospital's dashboard within seconds.
- Each hospital has **several user logins** and exactly **one dashboard** covering all of its forms. Hospital users can never see another hospital's data.
- The admin can see every hospital's dashboard plus a cross-hospital overview.

### 1.1 Glossary

| Term | Meaning |
|---|---|
| Hospital | A tenant / client account. All data belongs to exactly one hospital. |
| Typeform connection | The stored API token for our Typeform account. One connection in the MVP. |
| Form (form link) | A Typeform form linked to one hospital. One Typeform form belongs to at most one hospital. |
| Form version | A snapshot of the form's structure and our mapping of it. A new version is created when the Typeform form changes. |
| Section | A group of questions, derived from a Typeform *statement* block or *question group*, editable by the admin. |
| Field | One Typeform question within a form version. |
| Evaluation (submission) | One completed form response. Called "evaluation" in the UI. |
| Score | Section percentage and overall percentage for an evaluation. |

### 1.2 Roles

| Role | Scope | Can do |
|---|---|---|
| `super_admin` | Everything | Manage Typeform connection, hospitals, forms, mappings, all users; view all dashboards; review evaluations; audit log. |
| `hospital_admin` | Own hospital | View own dashboard and evaluations; export; invite / deactivate users of own hospital (as `hospital_viewer` or `hospital_admin`). |
| `hospital_viewer` | Own hospital | View own dashboard and evaluations; export. |

---

## 2. Tech stack (fixed decisions)

| Concern | Choice |
|---|---|
| Language / framework | PHP 8.3, **Laravel 11** |
| UI | **Livewire 3** + Blade + **Tailwind CSS** (via Vite), **Alpine.js** (ships with Livewire) |
| Charts | **Chart.js** installed via npm, bundled with Vite |
| Drag and drop | **SortableJS** via npm (mapping editor) |
| Auth scaffolding | **Laravel Breeze** (Livewire stack); registration disabled; invite-only |
| Database | **MySQL 8** (tests use MySQL too, via Docker, to match production behaviour; SQLite allowed only for fast unit tests that don't touch JSON columns) |
| Queue / cache | **Redis** (queue driver `redis`), Laravel Horizon optional |
| Scheduler | Laravel scheduler (`schedule:work` in dev, cron in prod) |
| HTTP client | Laravel `Http` facade (so tests can use `Http::fake()`) |
| PDF (milestone 7) | `barryvdh/laravel-dompdf` |
| Tests | **Pest 3** (+ `pestphp/pest-plugin-laravel`, `pest-plugin-livewire`) |
| Static analysis | **Larastan** level 6 |
| Formatting | **Laravel Pint** (default preset) |
| Local environment | **Docker Compose** (app, nginx, mysql, redis, queue worker, scheduler, mailpit) |
| CI | GitHub Actions: Pint check, Larastan, Pest with MySQL + Redis services |

Do not swap these choices. If something is impossible, record the problem and your alternative in `docs/DECISIONS.md` and continue.

---

## 3. Architecture

```
                    ┌──────────────────────────── Platform ─────────────────────────────┐
 Typeform  ──POST──▶│ POST /webhooks/typeform/{form_uuid}                               │
 (webhook)          │   verify signature → store delivery → 200 OK → dispatch job       │
                    │                                                                   │
                    │ Queue worker                                                      │
                    │   ProcessTypeformResponse: idempotency → version check →          │
                    │   normalise answers → score → save                                │
                    │                                                                   │
 Typeform  ◀──API───│ TypeformClient (forms, webhooks, responses, workspaces)           │
 (REST API)         │   used by: link form, backfill, reconcile, health check           │
                    │                                                                   │
 Browser   ◀──────▶ │ Livewire UI: admin area, hospital dashboard, evaluations          │
                    │   all reads from our database, scoped by hospital                 │
                    └───────────────────────────────────────────────────────────────────┘
```

Principles:

1. **The dashboard never calls Typeform.** It reads only from our database.
2. **Webhook requests do no heavy work.** Verify, store raw, respond `200`, then queue.
3. **Raw payloads are always kept**, so any submission can be re-scored later.
4. **Our mapping is the source of truth for scoring**, never Typeform's own `score` variable or logic. (The real form contains broken score logic; see section 17.)
5. **Tenant isolation is enforced server-side** in queries and policies, never only in the UI.

### 3.1 Code layout

```
app/
  Domain/
    Typeform/
      TypeformClient.php            # all HTTP calls to Typeform
      Data/                         # small readonly DTOs for API payloads
      FormStructureParser.php       # form JSON → sections + fields (pure, no I/O)
      WebhookSignature.php          # sign / verify (pure)
      AnswerNormalizer.php          # webhook/API answer → normalised value (pure)
    Forms/
      FormLinker.php                # link / unlink orchestration
      FormVersioner.php             # create versions, carry over admin mapping
      WebhookRegistrar.php          # register / check / repair webhooks
    Scoring/
      ScoringEngine.php             # normalised answers + version → scores (pure)
      ScoreResult.php
    Submissions/
      SubmissionIngestor.php        # idempotent create/update from payload
      ResponseSyncer.php            # backfill + reconcile via Responses API
    Dashboard/
      DashboardQuery.php            # all aggregate queries, always hospital-scoped
  Jobs/
    ProcessTypeformResponse.php
    BackfillFormResponses.php
    ReconcileFormResponses.php
    CheckFormWebhook.php
    CheckFormStructure.php
    RescoreFormSubmissions.php
  Http/Controllers/TypeformWebhookController.php
  Livewire/...                      # see sections 9-11
  Policies/...
  Models/...
tests/
  Unit/   Feature/   Fixtures/typeform/
docs/
  DECISIONS.md   PROGRESS.md   fixtures/ (provided by the project owner)
```

Keep domain classes framework-light and unit-testable. Parser, signature, normaliser and scoring engine must be pure (no database, no HTTP).

---

## 4. Data model

Use migrations exactly as below (column names may gain sensible additions, not removals). All tables have `id` bigint PK and `timestamps()` unless noted. Add the listed indexes.

### 4.1 `users`
| Column | Type | Notes |
|---|---|---|
| name | string | |
| email | string unique | |
| password | string | |
| role | enum `super_admin`, `hospital_admin`, `hospital_viewer` | |
| hospital_id | FK nullable → hospitals | null only for `super_admin` (enforce in model + validation) |
| is_active | bool default true | inactive users cannot log in |
| last_login_at | timestamp nullable | |
| remember_token | | Breeze default |

### 4.2 `hospitals`
| Column | Type | Notes |
|---|---|---|
| uuid | uuid unique | used in URLs |
| name | string | |
| slug | string unique | |
| logo_path | string nullable | stored on `public` disk |
| is_active | bool default true | inactive → users cannot log in, webhooks still stored |
| require_review | bool default false | if true, new evaluations start as `pending_review` (milestone 7) |

### 4.3 `branches` (milestone 7)
`hospital_id` FK, `name`, `code` nullable, `is_active`. Unique (`hospital_id`, `name`).

### 4.4 `typeform_connections`
| Column | Type | Notes |
|---|---|---|
| label | string | e.g. "Main Typeform account" |
| base_url | string | default `https://api.typeform.com`; EU accounts use `https://api.eu.typeform.com` |
| access_token | text, **encrypted cast** | personal access token; never logged, never sent to the browser |
| account_alias | string nullable | from `GET /me` if available |
| last_verified_at | timestamp nullable | |
| last_error | text nullable | |

### 4.5 `forms`
| Column | Type | Notes |
|---|---|---|
| uuid | uuid unique | **used in the webhook URL**; not guessable |
| hospital_id | FK | |
| typeform_connection_id | FK | |
| typeform_form_id | string **unique** | one Typeform form → one hospital |
| title | string | refreshed from Typeform on structure checks |
| webhook_tag | string | `emotion-{first 8 chars of uuid}` |
| webhook_secret | text, **encrypted cast** | random 40+ chars, per form |
| webhook_status | enum `active`, `missing`, `disabled`, `error`, `unregistered` | |
| webhook_checked_at | timestamp nullable | |
| webhook_last_error | text nullable | |
| current_version_id | FK nullable → form_versions | |
| settings | json | see 4.5.1 |
| status | enum `active`, `unlinked` | |
| last_synced_at | timestamp nullable | last successful backfill/reconcile |
| linked_by | FK users | |
| softDeletes | | |

#### 4.5.1 `forms.settings` (cast to a settings DTO with defaults)
```json
{
  "rag_green_min": 80,
  "rag_amber_min": 60,
  "yes_no_points": 10,
  "include_partial": false,
  "branch_source": null
}
```
`branch_source` (milestone 7): `null`, `{"type":"field","field_id":"..."}` or `{"type":"hidden","key":"branch"}`.

### 4.6 `form_versions`
| Column | Type | Notes |
|---|---|---|
| form_id | FK | |
| version_number | unsigned int | 1, 2, 3… per form; unique (`form_id`, `version_number`) |
| definition | json | full Typeform form JSON as fetched |
| definition_hash | string(64) | sha256 of the normalised structure (section 6.5) |
| needs_review | bool | true when new/unmapped fields were added automatically |
| reviewed_by | FK users nullable | |
| reviewed_at | timestamp nullable | |

### 4.7 `sections`
| Column | Type | Notes |
|---|---|---|
| form_version_id | FK | |
| name | string | cleaned name, editable |
| source_ref | string nullable | Typeform statement/group `ref`, used for carry-over |
| display_order | unsigned int | |
| weight | decimal(6,2) default 1.00 | `0` means section is shown but excluded from overall score |

### 4.8 `form_fields`
| Column | Type | Notes |
|---|---|---|
| form_version_id | FK | |
| typeform_field_id | string | Typeform `id` |
| typeform_ref | string nullable | Typeform `ref` |
| title | text | |
| description | text nullable | |
| type | string | Typeform field type |
| section_id | FK → sections | editable by admin |
| display_order | unsigned int | order within the form |
| is_scored | bool | defaults per section 7.2 |
| scale_min | int nullable | |
| scale_max | int nullable | |
| choices | json nullable | `[{id, ref, label}]` for choice fields |
| info_key | string nullable | one of `facility`, `location`, `visit_date`, `doctor`, `booking_method`, `branch`, or null |
| is_new_in_version | bool default false | flagged for admin review |

Unique (`form_version_id`, `typeform_field_id`). Index (`section_id`).

### 4.9 `webhook_deliveries`
| Column | Type | Notes |
|---|---|---|
| form_id | FK nullable | null if the UUID did not match |
| received_at | timestamp | |
| signature_valid | bool | |
| event_id | string nullable | |
| event_type | string nullable | |
| response_token | string nullable | |
| payload | longText | raw body exactly as received |
| processed_at | timestamp nullable | |
| error | text nullable | |

Prune deliveries older than 90 days (scheduled). Index (`form_id`, `received_at`).

### 4.10 `submissions`
| Column | Type | Notes |
|---|---|---|
| uuid | uuid unique | URLs |
| form_id | FK | |
| form_version_id | FK | version used for scoring |
| hospital_id | FK | **denormalised** for fast tenant scoping |
| branch_id | FK nullable | milestone 7 |
| response_token | string | Typeform response token |
| submitted_at | timestamp nullable | |
| landed_at | timestamp nullable | |
| is_partial | bool | |
| status | enum `pending_review`, `approved`, `rejected` | default `approved` unless `hospitals.require_review` |
| source | enum `webhook`, `backfill`, `reconcile` | first source that created it |
| raw | json | `{answers, hidden, variables, calculated, event_type}` |
| info | json | extracted summary fields keyed by `info_key` |
| points | decimal(10,2) nullable | |
| max_points | decimal(10,2) nullable | |
| overall_pct | decimal(5,2) nullable | null when nothing scorable was answered |
| rag | enum `green`, `amber`, `red` nullable | |
| scored_at | timestamp nullable | |
| reviewed_by / reviewed_at / review_note | | milestone 7 |

Unique (`form_id`, `response_token`). Indexes: (`hospital_id`, `status`, `submitted_at`), (`form_id`, `submitted_at`).

### 4.11 `answers`
| Column | Type | Notes |
|---|---|---|
| submission_id | FK cascade delete | |
| typeform_field_id | string | |
| form_field_id | FK nullable | null if field unknown to the version |
| answer_type | string | Typeform answer `type` |
| value_text | text nullable | display value |
| value_number | decimal(10,2) nullable | numeric answers |
| value_json | json nullable | lists, payment, etc. |
| points | decimal(8,2) nullable | null = not scored |
| max_points | decimal(8,2) nullable | |

Index (`submission_id`), (`form_field_id`).

### 4.12 `submission_section_scores`
`submission_id` FK cascade, `section_id` FK, `section_name` (snapshot), `display_order`, `points`, `max_points`, `pct` nullable, `weight`. Unique (`submission_id`, `section_id`).

### 4.13 `audit_logs`
`user_id` nullable, `hospital_id` nullable, `action` string (e.g. `form.linked`, `mapping.updated`, `user.invited`, `submission.rejected`), `subject_type`, `subject_id`, `changes` json, `ip`, `created_at` only.

### 4.14 `user_invitations`
`email`, `hospital_id` nullable, `role`, `token_hash`, `invited_by`, `expires_at` (72 h), `accepted_at`.

---

## 5. Typeform integration

All calls go through `TypeformClient`, which takes a `TypeformConnection`. Base URL from the connection. Header `Authorization: Bearer {token}`.

> **Verify every endpoint and payload shape against the official docs (developer.typeform.com) while implementing.** The shapes below are what this project was designed against; if the live docs differ, follow the docs and note it in `docs/DECISIONS.md`.

### 5.1 Endpoints used

| Purpose | Request |
|---|---|
| Verify token | `GET /me` |
| List workspaces | `GET /workspaces` |
| List forms | `GET /forms?page={n}&page_size=200&search={q}&workspace_id={id}` |
| Get form structure | `GET /forms/{form_id}` |
| Register / update webhook | `PUT /forms/{form_id}/webhooks/{tag}` body `{"url","enabled":true,"secret","verify_ssl":true}` |
| Check webhook | `GET /forms/{form_id}/webhooks/{tag}` |
| Delete webhook | `DELETE /forms/{form_id}/webhooks/{tag}` |
| List responses | `GET /forms/{form_id}/responses?page_size=1000&since=…&until=…&before=…&completed=true` |
| Download uploaded file | the `file_url` from an answer, requested with the bearer token |

Required token scopes: `accounts:read`, `forms:read`, `workspaces:read`, `webhooks:read`, `webhooks:write`, `responses:read`.

### 5.2 Client behaviour

- Timeouts: connect 5 s, total 20 s.
- Retry on `429` and `5xx` with exponential backoff (1 s, 2 s, 4 s; max 3 retries). Honour `Retry-After` if present.
- Throttle outgoing calls per connection to **2 requests/second** using Laravel's `RateLimiter` (Typeform rate-limits its APIs; check the current limit in the docs).
- Throw typed exceptions: `TypeformAuthException` (401/403), `TypeformNotFoundException` (404), `TypeformRateLimitException`, `TypeformException`.
- Never log the token or request headers. Log endpoint, status and duration only.

### 5.3 Connecting Typeform (admin)

Settings → Typeform: label, base URL (select: Global / EU), token (password input). On save: call `GET /me`; on success save encrypted, set `last_verified_at`; on failure show the error and do not save. Show connection status and a "Test connection" button. The token is never shown again after saving (show `tfp_…last4`).

### 5.4 Linking a form (admin)

UI: Hospital → Forms → **Link a form**. Shows a searchable list of Typeform forms (title, workspace, last updated) from `GET /forms`, excluding forms already linked. Admin selects one and confirms. Then `FormLinker::link()` runs inside a DB transaction where possible:

1. Create `forms` row (uuid, secret, tag, `webhook_status=unregistered`, default settings).
2. `GET /forms/{id}` → `FormVersioner::createInitialVersion()` (section 6) → set `current_version_id`, `needs_review = true`.
3. `WebhookRegistrar::register()` → `PUT` webhook with URL `{APP_URL}/webhooks/typeform/{form.uuid}` and the secret. On success `webhook_status=active`; on failure `error` + message (the link still succeeds; admin can retry).
4. Dispatch `BackfillFormResponses`.
5. Audit log `form.linked`.
6. Redirect the admin to the **mapping review screen** for the new form.

If `APP_URL` is not HTTPS, show a warning that Typeform requires a public HTTPS URL (use ngrok in local development).

### 5.5 Unlinking a form

Confirm dialog. `DELETE` the webhook (ignore 404), set `status=unlinked`, `webhook_status=unregistered`. Keep all submissions. Unlinked forms' evaluations remain visible on the dashboard (filterable). A form can be re-linked to the same hospital, which re-registers the webhook and reconciles. Moving a linked form to a different hospital is not allowed in the UI.

### 5.6 Webhook endpoint

`POST /webhooks/typeform/{form_uuid}`, excluded from CSRF, rate limited (120/min per IP), no session.

```
1. Read raw body exactly (do not re-encode JSON).
2. Find form by uuid (including unlinked). Not found → store delivery (form_id null, signature_valid false) → 404.
3. Verify header Typeform-Signature: "sha256=" + base64(HMAC_SHA256(raw_body, form.webhook_secret)).
   Use hash_equals. Invalid → store delivery (signature_valid false) → 401.
4. Store delivery (signature_valid true, event_id, event_type, response_token).
5. Dispatch ProcessTypeformResponse(delivery_id) on queue "webhooks".
6. Return 200 with {"ok":true}. Target < 300 ms.
```

### 5.7 Processing a response (`ProcessTypeformResponse`, also used by sync)

`SubmissionIngestor::ingest(Form $form, array $responsePayload, string $source)`:

1. Extract `token`, `submitted_at`, `landed_at`, `answers`, `hidden`, `variables`, `calculated`, `event_type`.
2. `is_partial` = `event_type` present and not equal to `form_response`. Responses API items without `submitted_at` are also partial.
3. **Idempotency:** lock on (`form_id`, `token`) (use `Cache::lock` or `SELECT … FOR UPDATE`). If a submission exists:
   - existing partial + incoming complete → update and re-score;
   - otherwise → mark delivery processed as duplicate and stop.
4. **Version check:** if any answer `field.id` is not in the current version's fields, run `FormVersioner::refreshFromTypeform()` (fetch form, create new version with carry-over, flag `needs_review`) before scoring. Max one refresh per form per 5 minutes (cache key) to avoid loops.
5. Normalise answers (section 7.1), save `answers`, extract `info` (fields with `info_key`; also `hidden` values with keys matching info keys).
6. Score with `ScoringEngine` (section 7) and save section scores, `points`, `max_points`, `overall_pct`, `rag`, `scored_at`.
7. Status: `pending_review` if hospital `require_review`, else `approved`.
8. Mark delivery `processed_at` (or `error` with message; job retries 3 times with backoff, then fails visibly in the admin "Deliveries" screen).

### 5.8 Backfill and reconcile (`ResponseSyncer`)

- **Backfill** (on link and via "Re-sync all" button): page through all completed responses (`before` token pagination, `page_size=1000`), ingest each with `source=backfill`.
- **Reconcile** (scheduled hourly for active forms): fetch responses with `since = last_synced_at − 24h`, ingest missing ones with `source=reconcile`. Update `last_synced_at` on success.
- Responses API answers have the same answer shapes as webhooks; they do not include the form definition, which is fine because mapping comes from our versions.

### 5.9 Scheduled health checks

| Job | Schedule | Behaviour |
|---|---|---|
| `CheckFormWebhook` | daily, each active form | `GET` webhook; missing → re-register (`PUT`), log `webhook.repaired`; wrong URL or disabled → re-register; failures → `webhook_status=error` |
| `CheckFormStructure` | every 6 h, each active form | `GET /forms/{id}`, compute `definition_hash`; if changed → new version with carry-over, `needs_review=true`; also refresh `forms.title` |
| `ReconcileFormResponses` | hourly | section 5.8 |
| Prune deliveries | daily | delete `webhook_deliveries` older than 90 days |

### 5.10 Uploaded files

Typeform `file_url` answers require authentication. Show uploads on the evaluation page via an authenticated, policy-checked route `GET /evaluations/{uuid}/files/{answer_id}` that streams the file from Typeform using the connection token. Never expose the raw Typeform URL with credentials to the browser. Images get an inline preview; other files a download link.

---

## 6. Form structure mapping

### 6.1 Parsing (`FormStructureParser::parse(array $formJson): ParsedStructure`)

Walk `formJson.fields` in order:

```
current_section = Section(name="General", source_ref=null)   # for questions before the first statement
for field in fields (depth-first):
    if field.type == "statement":
        start a new section: name = clean(field.title), source_ref = field.ref
        (drop "General" if it ended up empty)
    elif field.type == "group":
        create a section named clean(field.title), source_ref = field.ref
        its children (field.properties.fields, recursively) belong to that section
        after the group, following non-statement fields return to current_section
    else:
        add a ParsedField to current_section
```

Ignore `welcome_screens`, `thankyou_screens`, `logic`, `variables` for mapping (store them in `definition` anyway).

`clean(title)`: strip markdown (`*`, `_`), trim, then remove a leading section prefix using the case-insensitive regex `^\s*section\s*\d+\s*[—–\-:.]\s*`. If the result is empty, use the original title. Example: `"Section 2 — Website/ App booking"` → `"Website/ App booking"`.

### 6.2 Per-field defaults

| Typeform type | scale | `is_scored` default |
|---|---|---|
| `opinion_scale` | `steps` (default 11), `start_at_one` (default false) → min = start_at_one ? 1 : 0, max = min + steps − 1 | true |
| `rating` | min 1, max = `steps` (default 5) | true |
| `nps` | min 0, max 10 | true |
| `yes_no` | — | true |
| `dropdown`, `multiple_choice` whose choice labels, compared case-insensitively after trimming, include both "yes" and "no" and contain only yes / no / na / n/a / not applicable | — | true |
| everything else (text, date, number, file_upload, choice lists, etc.) | — | false |

`info_key` default by case-insensitive title match (first match wins per key):

| info_key | title contains any of |
|---|---|
| facility | "healthcare facility", "hospital name", "facility" |
| location | "location", "city" |
| visit_date | "visit date", "vsite date", "visite date", "date of visit" |
| doctor | "name of the doctor", "doctor name" |
| booking_method | "booking method" |

### 6.3 Initial version

`FormVersioner::createInitialVersion(Form, formJson)`: parse, create `form_versions` (version 1), `sections` in order, `form_fields` with defaults, `needs_review = true`.

### 6.4 New versions with carry-over (`refreshFromTypeform`)

When the structure changes:

1. Parse the new definition.
2. Create version N+1.
3. **Sections:** for each parsed section, if a section in version N has the same `source_ref`, copy its `name` (admin may have renamed it) and `weight`; otherwise use parsed defaults.
4. **Fields:** for each parsed field, if version N has the same `typeform_field_id`:
   - copy `is_scored` and `info_key`;
   - if the admin had moved it to a section whose `source_ref` still exists, keep it in that section; otherwise use the parsed section.
   - Scale min/max always come from the new definition.
5. Fields not present in version N get defaults and `is_new_in_version = true`; set version `needs_review = true` if any exist.
6. Point `forms.current_version_id` to the new version. Existing submissions keep their `form_version_id` and are **not** re-scored automatically.
7. Audit log `form.version_created`.

### 6.5 Structure hash

`definition_hash` = sha256 of JSON-encoding the ordered list of `[id, type, ref, title, properties.steps, properties.start_at_one, choice ids]` for every field (including statements and groups, recursively). Titles are included so renames create a version (cheap, and keeps snapshots honest).

### 6.6 Mapping editor (admin)

Route: `/admin/forms/{form}/mapping` (edits the **current** version only).

- Each section as a panel, in order: editable name, weight (0–10, step 0.5), up/down reorder, delete (only when empty), "Add section".
- Inside each section, its fields in order: title, type badge, scale (e.g. "0–10"), **Scored** toggle (disabled for unscorable types), **Info** select (`info_key` or none), "Move to…" select, and drag handle (SortableJS, also works across panels). Fields flagged `is_new_in_version` show a "New" badge.
- Sticky footer: "Save mapping". Saving validates (every field has a section; at most one field per `info_key`), writes changes, sets `needs_review=false`, `reviewed_by/at`, audit log `mapping.updated` with a diff.
- After save, if submissions exist for this version, show: "Recalculate scores for N evaluations?" → dispatch `RescoreFormSubmissions(form, version)`.
- Read-only view of previous versions with a version selector.

---

## 7. Scoring

### 7.1 Answer normalisation (`AnswerNormalizer`)

| answer `type` | value_text | value_number | value_json |
|---|---|---|---|
| `text`, `email`, `url`, `phone_number` | the string | — | — |
| `number` | string of number | number | — |
| `boolean` | "Yes" / "No" | 1 / 0 | — |
| `choice` | `choice.label`, or `choice.other` if label missing | — | `{id, ref, label, other}` |
| `choices` | labels joined with ", " (+ other) | — | `{ids, refs, labels, other}` |
| `date` | the date string (Y-m-d) | — | — |
| `file_url` | file name from URL | — | `{url}` |
| `payment` | formatted amount | amount | payment object |
| unknown | JSON-encoded value | — | raw |

### 7.2 Points per answer

Only for fields with `is_scored = true`:

| Field kind | points | max_points |
|---|---|---|
| scale (`opinion_scale`, `rating`, `nps`) | `value − scale_min` | `scale_max − scale_min` |
| yes/no (`yes_no` boolean, or yes/no choice field) | Yes → `settings.yes_no_points`; No → 0 | `settings.yes_no_points` |
| yes/no choice field answered NA / N/A / not applicable | **not scored** (both null) | |
| skipped (no answer present) | not scored | |

Scales are normalised by subtracting `scale_min`, so a 1–5 rating and a 0–10 scale both map to 0–100 %.

### 7.3 Section and overall scores

```
section.points     = Σ answer.points  (scored answers in the section)
section.max_points = Σ answer.max_points
section.pct        = max_points > 0 ? round(100 × points / max_points, 2) : null

overall_pct = weighted mean of section.pct over sections where pct != null and weight > 0
            = Σ(weight × pct) / Σ(weight)        → null if no such section
submission.points / max_points = sums over all scored answers (stored for reference)

rag = overall_pct ≥ rag_green_min → green
      overall_pct ≥ rag_amber_min → amber
      otherwise red  (null if overall_pct null)
```

Sections nobody answered (e.g. the website-booking section on a phone booking) have `pct = null` and are excluded, so they never drag the score down.

`ScoringEngine` is pure: input = form version mapping (sections, fields) + normalised answers + settings; output = `ScoreResult` (per-answer points, per-section totals, overall, rag). All persistence happens outside it.

### 7.4 Re-scoring

`RescoreFormSubmissions(form, version|null)`: for each submission of the form (optionally only those on a given version), re-normalise from `raw.answers`, score against **the submission's version** (or the current version if the admin chose "use latest mapping", which then also updates `form_version_id`), and save. Chunk by 200. Also available as `php artisan evaluations:rescore {form_uuid} [--latest]`.

---

## 8. Tenancy and authorisation

- Every query on hospital-owned data goes through a scope: a `BelongsToHospital` trait with a `scopeVisibleTo(User $user)` (super_admin → all; others → `hospital_id = user.hospital_id`).
- Policies for `Hospital`, `Form`, `Submission`, `User`. Hospital users only ever see their own hospital; they cannot access `/admin/*` (middleware `role:super_admin`).
- Hospital users see only `approved`, non-partial submissions (partial included only if `settings.include_partial`). Super admins see all statuses with filters.
- Route model binding for submissions uses `uuid` and must apply the visibility scope (a hospital user requesting another hospital's evaluation gets **404**, not 403).
- Inactive users and users of inactive hospitals are logged out and cannot log in.
- `super_admin` can open any hospital's dashboard at `/hospitals/{hospital:uuid}/dashboard` (same Livewire component, explicit hospital parameter, banner "Viewing as admin").

### 8.1 Users and invitations

- No public registration. Super admin creates hospitals and invites their users; `hospital_admin` invites users to their own hospital.
- Invite → email with a signed link (72 h) → set name + password → account created with the invited role and hospital. Store only a hash of the invite token.
- Super admin can deactivate/reactivate any user, change roles, resend invites. `hospital_admin` can do the same for their own hospital's users except they cannot deactivate themselves.
- Password reset via Breeze. Login throttling (5 attempts/min). Optional TOTP 2FA is out of scope for the MVP; leave a note in `DECISIONS.md`.
- First super admin is created by `php artisan app:create-super-admin` (interactive, or from env `SUPER_ADMIN_EMAIL` / `SUPER_ADMIN_PASSWORD` in the seeder for local only).

---

## 9. Dashboards

All numbers come from `DashboardQuery`, which always receives a resolved hospital (or "all hospitals" for the admin overview) and applies the visibility rules from section 8. Default period: last 90 days.

### 9.1 Hospital dashboard (`/dashboard` for hospital users, `/hospitals/{uuid}/dashboard` for admins)

Filters (persist in the query string): date range (presets: 30 days, 90 days, 12 months, custom), form (all or one), branch (milestone 7). When "all forms" is selected, section-level widgets show a prompt to pick a form, because sections differ between forms.

Widgets:
1. **Headline:** average overall score for the period (RAG coloured), number of evaluations, change vs the previous equal period (e.g. "+4.2 pts vs previous 90 days").
2. **Score trend:** line chart, average overall score per week (per month when range > 6 months). One line per form when "all forms" is selected (max 6; group the rest as "Other").
3. **Section averages** (single form): horizontal bars, average `pct` per section across evaluations, ordered by form order, RAG coloured, with evaluation count per section (sections are skipped by some evaluations).
4. **Weakest questions** (single form): the 10 scored questions with the lowest average `points / max_points`, with average and answer count.
5. **Yes/No compliance** (single form): for each yes/no question, % Yes among Yes+No answers.
6. **Recent evaluations:** latest 10 with date, form, facility/location info, overall score, link to detail.
7. **Score distribution:** count of evaluations in green / amber / red.

### 9.2 Evaluations list (`/evaluations`)

Paginated table (25/page): submitted date, form, facility, location, doctor, booking method, overall score (RAG pill), status (admins only). Filters: date range, form, RAG, search (facility/doctor/location text). Sort by date or score. **Export CSV** of the current filter: one row per evaluation with info fields, overall %, and one column per section % (only when a single form is selected; otherwise overall only). Stream the CSV (no memory blow-up on large exports).

### 9.3 Evaluation detail (`/evaluations/{uuid}`)

- Header: facility, location, visit date, doctor, booking method, form title, submitted time, overall score (large, RAG coloured), status badge (admins).
- **Journey strip:** one tile per section in form order, height/fill proportional to `pct`, value on top, "–" for unanswered sections; clicking a tile scrolls to and opens that section. (This is the signature visual; it already exists in the POC, see `docs/fixtures/poc_dashboard.py` if provided.)
- Sections as collapsible panels: name, `pct`, `points / max`, then every answer in form order: question, answer, and for scored answers the points (e.g. "8 / 10"). Long-text answers ("Additional Comments") are shown in full. Unscored answers are visually quieter.
- Uploaded files (section 5.10).
- Admin-only: "View raw payload" (pretty JSON, collapsed), mapping version used, re-score this evaluation, approve/reject (milestone 7).
- Print-friendly CSS; "Download PDF" in milestone 7.

### 9.4 Admin overview (`/admin`)

- Table of hospitals: name, active forms, evaluations in period, average overall score, trend arrow vs previous period, forms needing mapping review, webhook problems. Click → hospital page.
- Alerts panel: forms with `needs_review`, forms with `webhook_status != active`, failed deliveries in the last 24 h, Typeform connection errors.

---

## 10. Admin area screens

| Route | Screen |
|---|---|
| `/admin` | Overview (9.4) |
| `/admin/settings/typeform` | Typeform connection (5.3) |
| `/admin/hospitals` | List, search, create |
| `/admin/hospitals/create` | Name, logo, require_review |
| `/admin/hospitals/{uuid}` | Tabs: **Overview** (mini dashboard + link to full), **Forms** (linked forms with status, version, webhook status, last submission, actions), **Users** (list, invite, deactivate), **Settings** (name, logo, active, require_review) |
| `/admin/hospitals/{uuid}/forms/link` | Typeform form picker (5.4) |
| `/admin/forms/{uuid}` | Form detail: settings (RAG thresholds, yes/no points, include partial), webhook status + "Repair webhook", versions list, "Re-sync responses", "Recalculate scores", "Unlink" |
| `/admin/forms/{uuid}/mapping` | Mapping editor (6.6) |
| `/admin/deliveries` | Recent webhook deliveries: time, form, signature valid, processed/error; view payload; retry failed |
| `/admin/users` | All users across hospitals, filters, actions |
| `/admin/audit-log` | Filterable audit log |
| `/admin/review` | Review queue (milestone 7) |

Hospital-user screens: `/dashboard`, `/evaluations`, `/evaluations/{uuid}`, `/team` (hospital_admin only), `/profile`.

---

## 11. UI and design

- Livewire full-page components for each screen; small reusable Blade components for score pill, RAG badge, journey strip, section panel, filters bar, empty state.
- Visual direction (from the approved POC): clinical and calm. Font **Public Sans** (Google Fonts, with system fallback). Ink `#123A4A`, soft ink `#4E6875`, paper `#F7F9FA`, lines `#DCE3E7`, panels white, RAG green `#2F855A`, amber `#B7791F`, red `#B83A33`, neutral `#A9B6BD`. Never rely on colour alone: always show the number.
- Hospital users see their hospital's logo and name in the header.
- Responsive down to 375 px; visible keyboard focus; `prefers-reduced-motion` respected; WCAG AA contrast.
- Empty states tell the user what to do (e.g. "No evaluations yet. They appear here as soon as a survey is submitted.").
- Copy in sentence case, plain language: "evaluation", "survey", "section", "score". Avoid "webhook" in hospital-facing screens.

---

## 12. Security and privacy

- HTTPS only in production; `APP_URL` must be HTTPS for webhook registration.
- Secrets (`access_token`, `webhook_secret`) use Laravel's encrypted casts; `APP_KEY` must be backed up — losing it loses access to stored secrets.
- Webhook signature verified with `hash_equals` on the raw body; per-form secrets; unknown UUID → 404.
- CSRF everywhere except the webhook route. Rate limiting on login, invites, webhook route.
- Authorisation via policies and scopes (section 8); write feature tests proving cross-tenant access is impossible.
- No secrets, tokens or full payloads in logs. Exceptions reported without request bodies for the webhook route.
- Session cookies `secure`, `http_only`, `same_site=lax`. Security headers: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, a reasonable CSP compatible with Livewire/Vite.
- Audit log for: logins (success/failure count is enough), invites, role changes, deactivations, form link/unlink, mapping updates, re-scores, review decisions, Typeform connection changes.
- Evaluations contain staff names (doctors, nurses, agents). Treat as confidential: only visible within the hospital and to admins.

---

## 13. Configuration

`.env.example` must contain (with safe placeholders):

```
APP_NAME="eMotion Evaluations"
APP_URL=https://example.ngrok-free.app
DB_CONNECTION=mysql  DB_HOST=mysql  DB_DATABASE=emotion  DB_USERNAME=emotion  DB_PASSWORD=secret
QUEUE_CONNECTION=redis  REDIS_HOST=redis
MAIL_MAILER=smtp  MAIL_HOST=mailpit  MAIL_PORT=1025  MAIL_FROM_ADDRESS=no-reply@example.com
TYPEFORM_DEFAULT_BASE_URL=https://api.typeform.com
TYPEFORM_RATE_LIMIT_PER_SECOND=2
WEBHOOK_DELIVERY_RETENTION_DAYS=90
SUPER_ADMIN_EMAIL=admin@example.com      # local seeding only
SUPER_ADMIN_PASSWORD=change-me           # local seeding only
```

The Typeform token is **not** an env variable; it is entered in the admin UI and stored encrypted. Never commit a real token.

---

## 14. Testing

Use Pest. Never call the real Typeform API in tests: use `Http::fake()` and `Http::preventStrayRequests()` globally. Use model factories for every model.

### 14.1 Fixtures

The project owner will place real files in `docs/fixtures/`:
- `form_definition_rACXmOJ2.json`: real `GET /forms/{id}` response (10 statement sections, ~95 questions, broken score logic).
- `webhook_payload_rACXmOJ2.json`: real webhook test payload for that form.
- `poc_dashboard.py`: the working POC (reference for parsing and UI only).

If they are missing, create equivalent synthetic fixtures in `tests/Fixtures/typeform/` and continue. Also create small synthetic fixtures for: a form using **question groups**, a form with **no statements**, a form with a **1–5 rating** and a **yes_no** field, a **partial** response, a response with an **unknown field id**, and a **file upload** answer.

### 14.2 Required tests (minimum)

**Unit**
- Parser: real fixture produces 10 sections in order with cleaned names ("General Information", "Website/ App booking", …); "The website/ App was easy to access" lands in "General Information" (it sits above the Section 2 statement); questions before any statement go to "General"; groups produce sections with their children; statements/welcome/thank-you screens never become fields.
- Parser defaults: opinion_scale 11 steps start_at_one=false → 0–10; 5 steps start_at_one=true → 1–5; rating 5 → 1–5; nps → 0–10; Yes/No/NA dropdown → scored; "Location" dropdown → not scored; info_key detection for the real fixture's titles.
- Section name cleaning for "Section 2 — X", "section 10: X", "SECTION 3 - X", "Overall".
- Signature: known body + secret → known signature; tampered body fails; wrong prefix fails.
- Normaliser: every answer type in 7.1.
- Scoring: scale normalisation (1–5 and 0–10 both to %); Yes/No/NA; skipped; section pct null when unanswered; weighted overall; weight 0 excluded; all sections null → overall null; RAG thresholds at boundaries (59.99, 60, 79.99, 80).
- Versioner carry-over: renamed section keeps admin name; moved field stays moved; unscored stays unscored; new field flagged; removed field disappears; hash changes on title change only.

**Feature**
- Webhook: valid signature → 200, delivery stored, job dispatched; invalid signature → 401, nothing ingested; unknown uuid → 404; duplicate token → one submission; partial then complete → one submission, complete wins; unknown field id → version refresh (faked API) then scored.
- Linking flow with faked Typeform: version created, webhook `PUT` sent with correct URL/secret/tag, backfill dispatched, audit logged; webhook registration failure → form linked with `webhook_status=error`.
- Backfill/reconcile pagination with `before` tokens; reconcile inserts only missing responses.
- Health job re-registers a missing webhook; structure job creates a new version only when the hash changes.
- Mapping editor (Livewire): move field, rename section, toggle scored, set weight, save → audit log; validation errors; rescore dispatch.
- Re-score command recalculates scores after a mapping change.
- **Tenant isolation:** Ittefaq user cannot see CMH evaluations in the list, detail (404), dashboard numbers, CSV export or file proxy; hospital users get 403 on every `/admin/*` route; inactive user cannot log in.
- Dashboard numbers for a seeded dataset match hand-calculated values (averages, trend buckets, weakest questions, compliance %).
- CSV export content and headers.
- Invitations: invite, accept, expired link rejected, hospital_admin cannot invite into another hospital or create super_admins.

**Smoke**
- Every route renders for the right role with seeded data (no 500s) — a single data-provider test.

Coverage target: ≥ 90 % lines for `app/Domain`, ≥ 75 % overall (`--coverage` in CI, report only; do not block on overall).

---

## 15. Milestones

Work in order. At the end of each: run Pint, Larastan, full Pest suite; update `docs/PROGRESS.md` (what was built, how to verify, known gaps); commit with message `milestone N: <summary>`.

### M0 — Foundations
Laravel 11 app, Docker Compose (app/nginx/mysql/redis/worker/scheduler/mailpit), Breeze Livewire with registration disabled, Tailwind + design tokens, Pest/Larastan/Pint, GitHub Actions CI, `README.md` (setup, run, test, ngrok for webhooks), `docs/DECISIONS.md`, `docs/PROGRESS.md`.
**Done when:** `docker compose up` serves the login page; CI green; one passing test.

### M1 — Tenancy, users, roles
Migrations/models/factories for hospitals, users (roles), invitations, audit logs. `BelongsToHospital` scope, policies, role middleware. Super admin command. Hospital CRUD (admin). Invitations flow, team screen, user management, deactivation. Audit logging service.
**Done when:** admin creates "Ittefaq Hospital" and "CMH", invites users, they log in and see an empty dashboard for their own hospital only; isolation tests pass.

### M2 — Typeform connection and client
`typeform_connections`, `TypeformClient` (all endpoints in 5.1, retries, throttle, exceptions), settings screen with test connection, form picker listing forms (search, workspace filter, pagination).
**Done when:** with a faked API the picker lists forms; client unit tests cover retries/429/401.

### M3 — Linking, structure parsing, versions, mapping editor
Forms, versions, sections, fields tables. Parser, versioner (initial + carry-over + hash), `WebhookRegistrar`, `FormLinker`, unlink, form detail screen, mapping editor with drag and drop, previous-version view.
**Done when:** linking the real fixture produces the expected 10 sections; admin can fix the misplaced website question, rename sections and save; all M3 tests pass.

### M4 — Ingestion and scoring
Webhook controller, deliveries, `ProcessTypeformResponse`, normaliser, ingestor (idempotent, partial handling, version refresh), scoring engine, re-score job + command, backfill/reconcile syncer, scheduled jobs (5.9), deliveries screen with retry.
**Done when:** posting the real webhook fixture (signed with the form's secret) creates one scored evaluation with correct section percentages; duplicates and partials behave as specified; end-to-end test from link → webhook → scored evaluation passes.

### M5 — Hospital dashboard and evaluations
`DashboardQuery`, dashboard widgets (9.1), evaluations list + filters + CSV export (9.2), evaluation detail with journey strip and file proxy (9.3), admin "view as hospital".
**Done when:** seeded demo data renders all widgets; numbers match the hand-calculated test; isolation tests extended to these screens pass.

### M6 — Admin overview and operations
Admin overview with alerts (9.4), webhook repair button, re-sync, audit log screen, all-users screen.
**Done when:** a form with a deleted webhook shows an alert and "Repair webhook" fixes it (faked API).

### M7 — Phase 3 features
Review workflow (`require_review`, review queue, approve/reject with note, hospital users see approved only), branches (`branch_source` from field or hidden field, branch filter on dashboard and list), PDF export of an evaluation (dompdf, same layout as detail), optional workspace auto-linking (map a Typeform workspace to a hospital; a scheduled job links new forms in that workspace automatically, with `needs_review` so the admin still checks the mapping).
**Done when:** each feature has tests and is reachable from the UI.

### M8 — Hardening and handover
Security headers and CSP, performance pass (indexes verified with `EXPLAIN` on dashboard queries; dashboard < 1 s with 50 000 seeded evaluations — add a `--large` option to the demo seeder), error pages, `README.md` complete (including production deployment: queue worker, scheduler cron, HTTPS, backups, `APP_KEY` care), `docs/RUNBOOK.md` (webhook not arriving, re-scoring, rotating the Typeform token, adding a hospital, linking a form).
**Done when:** full suite green; Larastan clean; manual checklist in section 16 passes.

---

## 16. Definition of done (whole project)

- [ ] `docker compose up` + `php artisan migrate --seed` gives a working app with demo data: super admin, Ittefaq Hospital and CMH, 2 forms each (built from the real fixture structure), ~300 evaluations over 12 months with realistic score variation, a few users per hospital.
- [ ] Admin can connect a real Typeform token, link a real form, see the webhook created in Typeform, submit the form in Typeform, and see the scored evaluation on the right hospital's dashboard within a minute (with `APP_URL` on a public HTTPS tunnel).
- [ ] Mapping editor fixes misplaced questions; re-score updates historical evaluations.
- [ ] Editing the form in Typeform creates a new version on the next submission or structure check, keeps admin mapping, flags new questions.
- [ ] Hospital users only ever see their own hospital (proved by tests).
- [ ] Deleted webhook is detected and repaired.
- [ ] CSV and PDF exports work.
- [ ] CI green: Pint, Larastan level 6, Pest with coverage report.
- [ ] `README.md`, `docs/RUNBOOK.md`, `docs/DECISIONS.md`, `docs/PROGRESS.md` are complete and accurate.

---

## 17. Known facts about the real form (rACXmOJ2 / pBmRBTmf)

Use these in fixtures and tests; do not "fix" the form from code.

- Structure: welcome screen, then 10 statements named "Section 1 — General Information" … "Section 10 — Overall", each followed by its questions; a final file upload question.
- Opinion scales are **11 steps, start_at_one = false (0–10)**, although several question descriptions say "1 to 5" or "1 to 10". Trust the properties, not the descriptions.
- "The website/ App was easy to access" is placed **above** the Section 2 statement, so automatic mapping puts it in General Information. This is the main example for the mapping editor.
- "Healthcare Facility" is **free text**, so facility names will vary in spelling. Treat it as display info only; branches (M7) need a dropdown or hidden field.
- "Appointment Booking method" logic jumps phone bookings to Section 3, otherwise to Section 2, so one of those two sections is normally unanswered (pct = null, excluded).
- Yes/No/NA dropdowns exist in several sections.
- The form's own score logic is unreliable (both Yes and No subtract 1 on one question; a rating of 5 adds 3 on another; one `always` jump returns to Section 1). **Ignore `calculated.score` entirely.**
- The form has `partial_responses_to_all_integrations: true`, so partial responses may arrive by webhook.
- "Additional Comments" appears in most sections; titles repeat, so always key by field id.

---

## 18. Working rules for Claude Code

1. Read this whole document first. Create `docs/PROGRESS.md` with the milestone checklist and keep it updated.
2. Work milestone by milestone. Do not start the next milestone with failing tests.
3. Write tests alongside code; write domain tests (parser, scoring, signature, versioner) **before** their implementation.
4. When the spec is ambiguous, choose the simplest option consistent with sections 1–3, record it in `docs/DECISIONS.md` (date, question, decision, reason), and continue. Do not stop to ask unless something is truly blocking.
5. Never call real external services in tests. Never commit secrets. Never print tokens.
6. Keep controllers and Livewire components thin; business logic lives in `app/Domain`.
7. Prefer clarity over cleverness. No unused abstractions, no speculative features beyond this spec.
8. Run `./vendor/bin/pint`, `./vendor/bin/phpstan analyse`, `php artisan test` before every commit.
9. If a Typeform API detail differs from this spec, follow the official docs, adjust tests, and note it in `DECISIONS.md`.
10. At the end, give a short handover summary: how to run it, how to link a real form, what was verified manually, and any known limitations.
