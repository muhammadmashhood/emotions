# Runbook

Operational procedures for the eMotion Evaluations platform. All admin actions are audited (`Admin → Audit log`).

## A webhook is not arriving

1. **Is the form linked and its webhook active?** `Admin → Overview` lists forms whose webhook is not `active`; the form page shows the registered endpoint, last check and last error.
2. **Is `APP_URL` public HTTPS and does it match the registered URL?** The daily `forms:health webhooks` check compares the webhook URL with `APP_URL` and re-registers it; you can force it with **Repair webhook**. After changing `APP_URL` (new ngrok tunnel, new domain) repair every active form or run `php artisan forms:health webhooks` and let the queue process the jobs.
3. **Did the request reach us?** `Admin → Deliveries` shows every POST, including ones with an invalid signature (401) or unknown uuid (404). Nothing listed → Typeform did not send (check the form's *Connect → Webhooks* panel in Typeform; the "Test" button there should produce a delivery) or nginx is not forwarding `/webhooks/typeform/*`.
4. **Delivery stored but "Pending"?** The queue worker is not running or is stuck. Check `php artisan queue:work` is up, `php artisan queue:failed`, and Redis connectivity. Pending deliveries are processed as soon as the worker runs; **Retry** re-queues a failed one.
5. **Delivery "Failed"?** Open the payload and the error text. Typical causes: the form has no current version (re-link), or Typeform was unreachable during a structure refresh (the response is still scored on the current version; a later `forms:health structure` run creates the missing version).
6. **Nothing lost:** the hourly `forms:health reconcile` pulls responses from the Typeform Responses API since the last sync − 24 h and inserts whatever the webhook missed. "Re-sync responses" on the form page does a full backfill.

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

## Other useful commands

| Command | Purpose |
|---|---|
| `php artisan app:create-super-admin` | create a super admin (production) |
| `php artisan forms:health {webhooks\|structure\|reconcile}` | dispatch the scheduled checks now |
| `php artisan forms:auto-link` | link new forms from mapped workspaces now |
| `php artisan deliveries:prune [--days=90]` | delete old webhook deliveries |
| `php artisan app:seed-demo [--large]` | demo data (never in production) |
| `php artisan queue:failed` / `queue:retry all` | inspect / retry failed jobs |
