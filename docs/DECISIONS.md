# Decisions

Record of choices made where the spec was ambiguous, impossible, or where the environment differed.

| Date | Question | Decision | Reason |
|---|---|---|---|
| 2026-10-16 | Local PHP is 8.2 (and 8.4); spec says 8.3. | Docker image and CI use PHP 8.3. Local development/tests run on the machine's default PHP 8.2, which Laravel 11 supports. | No PHP 8.3 binary on the build machine; nothing in the code base depends on 8.3-only syntax. |
| 2026-10-16 | Breeze ships registration and email verification. | Registration route, view and tests removed. Email verification removed: users only ever exist through an accepted invitation, which proves ownership of the email. | Spec section 8.1: invite-only, no public registration. |
| 2026-10-16 | Breeze ships a "delete my account" form. | Removed. Accounts are deactivated by admins instead of deleted by the user. | Audit trail and tenant admin control (section 8.1). |
| 2026-10-16 | Redis client. | `predis/predis` (pure PHP) instead of the `phpredis` extension. | The extension is not available in the local PHP; predis works everywhere, including CI. |
| 2026-10-16 | Google Fonts vs Bunny Fonts. | Public Sans is loaded from Google Fonts as the spec says, with system-ui fallback. | Spec section 11. |
| 2026-10-16 | Test database. | Tests run against MySQL 8 (`127.0.0.1:3307`, database `emotion_test`, root/secret) via Docker, both locally and in CI. | Spec section 2: tests use MySQL to match production JSON column behaviour. |
| 2026-10-16 | TOTP 2FA. | Out of scope for the MVP (spec 8.1). Login throttling (5/min) from Breeze is kept. | Spec. |
| 2026-10-16 | `GET /forms` items do not carry a workspace name, but the picker must show one. | When "All workspaces" is selected the catalog lists workspaces and calls `GET /forms?workspace_id=…` once per workspace (first 200 forms each), caching the result for 60 s. | Keeps the API call count low (Typeform allows ~2 req/s) while still showing the workspace column. |
| 2026-10-16 | "max 3 retries" vs Laravel `Http::retry($times)` which counts total attempts. | The client passes `max_retries + 1` so a failing call is attempted 4 times (1 + 3 retries). | Matches the spec wording literally. |
| 2026-10-16 | Carry-over for sections the admin created in the editor (no Typeform `ref`). | Admin-created sections get a synthetic `source_ref` of `admin-xxxxxxxx` and are copied into every new version together with the fields moved into them. | Spec 6.4 keys carry-over on `source_ref`; without one, admin sections would vanish on the next Typeform edit. |
| 2026-10-16 | Detecting "the admin had moved a field" (6.4 step 4). | The previous version's definition is re-parsed; if the stored section differs from where parsing would have placed the field, it counts as an admin move and is kept when that section still exists. | Otherwise a Typeform-side move of a question could never take effect. |
| 2026-10-16 | Field `display_order` after the admin reorders fields inside a section. | On save the editor renumbers `display_order` globally following section order then field order. | Keeps one simple ordering for the detail page and CSV columns. |
| 2026-10-16 | Section ordering on new versions. | Sections matched by `source_ref` keep the parsed order; admin-created sections are appended. | Simplest rule consistent with 6.4, which only requires name and weight to carry over. |
