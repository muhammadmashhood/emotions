# Runbook

Operational procedures for the eMotion Evaluations platform. All admin actions are audited (`Admin → Audit log`).

## A webhook is not arriving

1. **Is the form linked and its webhook active?** `Admin → Overview` lists forms whose webhook is not `active`; the form page shows the registered endpoint, last check and last error.
2. **Is `APP_URL` public HTTPS and does it match the registered URL?** The daily `forms:health webhooks` check compares the webhook URL with `APP_URL` and re-registers it; you can force it with **Repair webhook**. After changing `APP_URL` (new ngrok tunnel, new domain) repair every active form or run `php artisan forms:health webhooks` and let the queue process the jobs.
3. **Did the request reach us?** `Admin → Deliveries` shows every POST, including ones with an invalid signature (401) or unknown uuid (404). Nothing listed → Typeform did not send (check the form's *Connect → Webhooks* panel in Typeform; the "Test" button there should produce a delivery) or nginx is not forwarding `/webhooks/typeform/*`.
4. **Delivery stored but "Pending"?** The queue worker is not running or is stuck. Check `php artisan queue:work` is up, `php artisan queue:failed`, and Redis connectivity. On SiteGround the worker is a cron job: make sure it is registered for the right site, uses the real account path (copy it from File Manager, it is **not** `/home/customer/...`) and `/usr/local/bin/php`, and redirect its output to `storage/logs/queue.log` to prove it runs. Pending deliveries are processed as soon as the worker runs; **Retry** re-queues a failed one.
5. **Delivery "Failed"?** Open the payload and the error text. Typical causes: the form has no current version (re-link), or Typeform was unreachable during a structure refresh (the response is still scored on the current version; a later `forms:health structure` run creates the missing version).
6. **Nothing lost:** the hourly `forms:health reconcile` pulls responses from the Typeform Responses API since the last sync − 24 h and inserts whatever the webhook missed. "Re-sync responses" on the form page does a full backfill.

## Roles

| Role | Belongs to | Can |
|---|---|---|
| eMotion admin (`super_admin`) | eMotion | everything: hospitals, forms, users, settings, review, edit evaluations |
| eMotion QA (`emotion_qa`) | eMotion | review queue, every hospital's dashboard and evaluations, approve / reject / re-score / edit evaluations, add supporting documents. No hospital, user, form or settings management. Lands on the review queue. |
| Hospital admin | one hospital | that hospital's dashboard and evaluations; invites and deactivates its own users |
| Viewer | one hospital | that hospital's dashboard and evaluations |

Only an eMotion admin can invite eMotion staff (`Admin → Users & invitations`, role "eMotion QA", no hospital).

## Editing a submitted evaluation

eMotion staff can correct an evaluation from its detail page: **Admin tools → Edit evaluation** turns the points of every scored question and the shopper comments into inputs. Saving re-scores the evaluation immediately, keeps the original values (shown on the "edited" pill), and writes a `submission.edited` audit row listing every change. Edits survive form-wide recalculations because they are stored as overrides on the evaluation, not by rewriting the Typeform payload. **Supporting documents** (SMS confirmations, invoices, photos) can be added below the touchpoints; they are visible to the hospital and embedded in the PDF.

## Attachments

File answers are copied from Typeform to `storage/app/private/attachments/{evaluation uuid}/` by a queued job right after ingestion, so the PDF can embed them and they outlive a token rotation. If the copy failed (token revoked, network), the evaluation page falls back to streaming the file from Typeform with the current token; the warning is in `storage/logs/laravel-*.log` as `attachment.download_failed`.

## Deleting a hospital or a form

- **Hospital page → Settings → Danger zone**: type the hospital name to confirm. Removes its forms (webhooks are dropped at Typeform first; a revoked token does not block the deletion), evaluations, attachments, users and invitations. The audit log keeps a `hospital.deleted` row with the counts.
- **Hospital page → Forms tab**: **Unlink** removes the webhook and keeps the evaluations visible; **Delete** removes the form, its mapping versions and all its evaluations. Delivery history is kept.

## Re-scoring evaluations

Scores are derived from the stored raw answers and the form version's mapping, so they can always be recomputed.

- After **saving a mapping**, the editor offers "Recalculate scores for N evaluations" for that version.
- On the **form page**, "Recalculate scores" re-scores every evaluation of the form against its own version, or against the current mapping when "Use the current mapping for all evaluations" is ticked (this also moves them to the current version).
- A **single evaluation** can be re-scored from its detail page (admin tools).
- CLI: `php artisan evaluations:rescore {form_uuid} [--latest] [--on-version=N]`.
- Changing RAG thresholds or Yes/No points in the form settings only affects new evaluations until you recalculate.
- Branch sources are re-resolved on re-score too, so configuring a branch source later and recalculating back-fills branches.

## Rotating the Typeform token

1. Create a new personal access token in Typeform (scopes `accounts:read forms:read workspaces:read webhooks:read webhooks:write responses:read`).
2. `Admin → Settings → Typeform` → paste it → **Verify and save**. The token is checked with `GET /me` before anything is stored; the old token is replaced in place (encrypted with `APP_KEY`).
3. Click **Test connection**. Existing webhooks keep working (they are tied to the form, not the token). Revoke the old token in Typeform.
4. If the connection shows an error later (expired/revoked token), the Overview alerts panel reports it and backfills / structure checks will fail until a new token is saved. Webhook deliveries are still stored and processed because they do not need the token.

## Adding a hospital

1. `Admin → Hospitals → New hospital`: name, optional logo, "Require review" if evaluations must be approved before hospital users see them.
2. Invite users from the hospital page → **Users** tab (hospital admin or viewer). They receive a 72-hour link, set a name and password, and land on their dashboard. Hospital admins can invite / deactivate their own colleagues from **Team**.
3. Optionally map a Typeform workspace in the **Settings** tab so new forms in that workspace are linked automatically (still flagged for mapping review).
4. Deactivating a hospital logs its users out and blocks login; webhooks are still stored.

## Linking a form

1. Hospital page → **Link a form** → choose the Typeform form (forms already linked elsewhere are hidden; a form previously unlinked from this hospital can be re-linked).
2. The platform fetches the structure, registers the webhook and queues a backfill. If webhook registration fails the form is still linked with status `error`; fix the cause (usually `APP_URL`) and use **Repair webhook**.
3. Review the mapping: every statement block became a section; questions above the first statement are in "General". Drag misplaced questions, rename sections, set weights (0 excludes a section from the overall score), toggle *Scored*, assign *Info* keys (facility, location, visit date, doctor, booking method, branch), then **Save mapping**.
4. When the form changes in Typeform, a new version is created automatically (on the next submission with unknown questions, or by the 6-hourly structure check). Your names, weights, moves and scored flags carry over; new questions are flagged "New" and the form shows "Needs review".
5. **Unlink** removes the webhook and keeps every evaluation visible. A linked form cannot be moved to another hospital.

## Invitation email returns a 500 / "could not be emailed"

The mail transport is not configured. Set the real SMTP values in the server `.env` (on SiteGround: `Site Tools → Email → Accounts`, outgoing server `<server>.siteground.biz`, port 465, SSL, the mailbox as username) and run `php artisan config:cache`. The invite form now shows the error instead of crashing, and the invitation row is rolled back so you can send it again.

## Hospital logos not loading

Logos are stored in `storage/app/public/logos` and served through the `public/storage` symlink (`php artisan storage:link`, run by `post-deploy.sh`). On SiteGround the root `.htaccess` must forward `/storage/...` to `public/storage/...` (the stock `deploy/siteground/root.htaccess` does); a copy that denies `storage` at the root returns 403 for every logo.

## Other useful commands

| Command | Purpose |
|---|---|
| `php artisan app:create-super-admin` | create a super admin (production) |
| `php artisan forms:health {webhooks\|structure\|reconcile}` | dispatch the scheduled checks now |
| `php artisan forms:auto-link` | link new forms from mapped workspaces now |
| `php artisan deliveries:prune [--days=90]` | delete old webhook deliveries |
| `php artisan app:seed-demo [--large]` | demo data (never in production) |
| `php artisan app:purge-demo-data --force` | remove demo hospitals/forms/evaluations if a local database was imported into production |
| `php artisan queue:failed` / `queue:retry all` | inspect / retry failed jobs |

## Workspace auto-linking linked the wrong forms

A hospital mapped to a Typeform workspace (Settings → "Typeform workspace") gets forms from that workspace linked by the hourly `forms:auto-link` job. Since 17 Nov 2026 only forms **created after the mapping was saved** qualify (`hospitals.typeform_workspace_mapped_at`); the migration stamps existing mappings with the deploy time so nothing old is picked up.

If a mapping to a shared workspace already pulled in forms that do not belong to the hospital:

```
php artisan forms:purge-auto-linked          # dry run: lists every automatically linked form
php artisan forms:purge-auto-linked --force  # removes them, their webhooks, versions and imported evaluations
```

Forms linked by a person (audit row `form.linked` without `automatic`) are never touched. Clear the workspace on the hospital first, or the next hourly run links new forms again.

## Rejected surveys and scores

Rejected and pending-review surveys never enter any score, average, count, ranking or heat-map cell, for any viewer: `DashboardQuery::base()` applies the same approved-only rule (plus the partial-response rule) to eMotion staff as to hospital users, so both sides always see identical figures. Staff still find rejected and pending surveys in a hospital's Evaluations list via the Status filter, on the admin overview "Rejected surveys" tile, and in the staff CSV export's Status column. Re-approving a survey puts it straight back into the numbers.

## Creating a user without email

When an invitation email does not arrive, create the account directly: on the eMotion Users page ("Invite user" → "Set a temporary password") or on a hospital's Team tab. Enter name, email, role and a temporary password (or press Generate), then copy the password from the green panel; it is shown only once and is never emailed. The same permission rules as invitations apply, and the action is audited as "Created user". At first sign-in the person is taken to a "Set your password" page and cannot see anything else until they choose their own. If they lose the temporary password before signing in, deactivate the account and create it again, or use "Forgot password" if their mail works.

## Evaluation email notifications

Two emails, one per recipient, queued on the database queue. "New evaluation" goes to every active Super Admin and QA user when a complete response arrives through the live Typeform webhook. "Evaluation published" goes to the active Hospital Admins of that hospital when an evaluation becomes approved: on arrival for hospitals without review, or when staff press Approve (re-approving after a rejection sends it again). Nothing is sent for backfills, reconciliation, partial responses, duplicates, rejections, edits or re-scores. If emails are missing: check that the queue worker cron is running, look at the `jobs` and `failed_jobs` tables, and grep the daily log for `evaluation.notify_failed`.
