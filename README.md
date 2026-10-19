# eMotion Evaluations

Multi-tenant platform that turns **Typeform mystery-shopping surveys** into **scored dashboards for hospitals**.

- The admin connects a Typeform account, creates hospital accounts and links Typeform forms to them.
- Linking reads the form structure, maps questions to sections, registers a webhook and backfills responses.
- Every submission is verified, stored raw, scored per section with *our* mapping and shown on the hospital's dashboard within seconds.
- Hospital users only ever see their own hospital; the admin sees everything plus a cross-hospital overview.

Stack: PHP 8.3 · Laravel 11 · Livewire 3 + Alpine · Tailwind · Chart.js · SortableJS · MySQL 8 · Redis · Pest 3 · Larastan 6 · Pint · Docker Compose.

Documents: [`SPEC.md`](SPEC.md) (specification), [`docs/PROGRESS.md`](docs/PROGRESS.md) (what was built per milestone and how to verify it), [`docs/DECISIONS.md`](docs/DECISIONS.md) (recorded decisions), [`docs/RUNBOOK.md`](docs/RUNBOOK.md) (operations).

---

## 1. Quick start with Docker

```bash
cp .env.example .env
docker compose up -d --build            # app (php-fpm), nginx, mysql, redis, worker, scheduler, mailpit
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
open http://localhost:8080/login
```

`migrate --seed` creates:

| Login | Password | Role |
|---|---|---|
| `admin@example.com` | `change-me` | super admin (from `SUPER_ADMIN_EMAIL` / `SUPER_ADMIN_PASSWORD`, local only) |
| `admin@ittefaq.example.com`, `viewer@ittefaq.example.com` | `password` | Ittefaq Hospital |
| `admin@cmh.example.com`, `viewer@cmh.example.com` | `password` | CMH |

plus two forms per hospital (built from the real form structure) and ~300 evaluations over 12 months. Invitation emails land in Mailpit at http://localhost:8025.

Ports can be changed in `.env`: `APP_PORT` (default 8080), `FORWARD_DB_PORT`, `FORWARD_REDIS_PORT`, `FORWARD_MAILPIT_PORT`.

## 2. Native development

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# point DB_HOST/DB_PORT/DB_USERNAME, REDIS_HOST and MAIL_* at your local services, then:
php artisan migrate --seed
npm run dev                                                   # Vite with hot reload
php artisan serve                                             # http://localhost:8000
php artisan queue:work redis --queue=webhooks,default --tries=3 --backoff=10,60,300
php artisan schedule:work
```

Large dataset for performance work: `php artisan app:seed-demo --large` (≈50 000 evaluations, bulk-inserted).

## 3. Tests and static analysis

Tests run against **MySQL** so JSON columns behave like production. Start a throwaway MySQL on port 3307:

```bash
docker run -d --name emotion-test-mysql -e MYSQL_ROOT_PASSWORD=secret -e MYSQL_DATABASE=emotion_test \
  -p 127.0.0.1:3307:3306 mysql:8.0
./vendor/bin/pest                       # full suite (unit + feature + smoke), never calls Typeform
./vendor/bin/pest --coverage            # coverage report (needs xdebug or pcov)
./vendor/bin/phpstan analyse            # Larastan level 6
./vendor/bin/pint --test                # formatting
```

CI (`.github/workflows/ci.yml`) runs Pint, Larastan and Pest with MySQL + Redis services and a coverage report.

Fixtures: the project owner's real files are read from `docs/fixtures/` when present; otherwise the synthetic equivalents in
`tests/Fixtures/typeform/` are used (regenerate with `php tests/Fixtures/typeform/generate.php`).

## 4. Webhooks in development (ngrok)

Typeform only delivers webhooks to **public HTTPS** URLs.

```bash
ngrok http 8080
# .env: APP_URL=https://<your-id>.ngrok-free.app   then restart the containers
```

Forms linked before the URL change can be fixed with **Repair webhook** on the form page (or wait for the daily check).

## 5. Linking a real form

1. Log in as super admin → **Settings → Typeform**. Paste a personal access token with scopes
   `accounts:read forms:read workspaces:read webhooks:read webhooks:write responses:read`. It is verified with `GET /me`
   and stored encrypted; only `tfp_…last4` is ever shown again.
2. **Hospitals → New hospital**, then on the hospital page **Link a form** and pick the Typeform form (search, workspace filter).
3. The platform fetches the structure, creates version 1 with sections derived from the form's statement blocks,
   registers the webhook (`{APP_URL}/webhooks/typeform/{uuid}`) and backfills existing responses in the background.
4. You land on the **mapping editor**: drag misplaced questions into the right section (e.g. "The website/ App was easy to
   access" into *Website/ App booking*), rename sections, set weights, toggle *Scored*, assign *Info* keys, then **Save mapping**.
   If evaluations already exist you can recalculate their scores.
5. Submit the form in Typeform; the evaluation appears on the hospital dashboard within seconds (`Admin → Deliveries` shows every request).

Optional: map a Typeform **workspace** to a hospital (hospital Settings tab) and new forms in that workspace are linked
automatically every hour, flagged for mapping review.

## 6. How scoring works (short)

- Scales (`opinion_scale`, `rating`, `nps`) score `value − min` out of `max − min`, so 1–5 and 0–10 both map to 0–100 %.
- Yes/No questions (and Yes/No/NA dropdowns) score `yes_no_points` for Yes, 0 for No; NA is not scored.
- Section % = points / max points of the answered, scored questions. Unanswered sections are `null` and excluded.
- Overall % = weighted mean of section % (weight 0 = shown but excluded). RAG thresholds are per form (default 80 / 60).
- Typeform's own `score` variable is ignored. Raw payloads are kept so everything can be re-scored
  (`Recalculate scores` in the UI or `php artisan evaluations:rescore {form_uuid} [--latest]`).

## 7. Production deployment

- **Requirements:** PHP 8.3 (fpm) with `pdo_mysql mbstring bcmath intl zip gd`, MySQL 8, Redis 7, nginx, Node 20 for the asset build (or use the Docker image, which builds assets in a stage).
- **Environment:** copy `.env.example`; set `APP_ENV=production`, `APP_DEBUG=false`, a **public HTTPS** `APP_URL`, `SESSION_SECURE_COOKIE=true`, real `DB_*`, `REDIS_*`, `MAIL_*`. Remove `SUPER_ADMIN_*`; create the first admin with `php artisan app:create-super-admin`.
- **Build:** `composer install --no-dev --optimize-autoloader && npm ci && npm run build && php artisan migrate --force && php artisan config:cache route:cache view:cache event:cache`.
- **Queue worker** (webhook processing, backfills, re-scores): run under a supervisor, e.g.
  `php artisan queue:work redis --queue=webhooks,default --sleep=1 --tries=3 --backoff=10,60,300 --max-time=3600`.
  Restart workers on every deploy (`php artisan queue:restart`).
- **Scheduler** (hourly reconcile + auto-link, 6-hourly structure check, daily webhook check and delivery pruning):
  cron `* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1`.
- **HTTPS only.** Typeform refuses non-HTTPS webhook URLs and the session cookie is `secure`. Terminate TLS at nginx or a load balancer and make sure `APP_URL` matches the public origin (webhooks are registered with it).
- **Security headers / CSP** are added by `App\Http\Middleware\SecurityHeaders`; add trusted script origins via `CSP_EXTRA_SOURCES` if you add an error tracker.
- **Backups:** back up MySQL daily (the `submissions.raw` column holds every original payload) **and `APP_KEY`**. The Typeform token and per-form webhook secrets are encrypted with `APP_KEY`; losing the key means re-entering the token and re-registering webhooks ("Repair webhook" does it per form). Never rotate `APP_KEY` without re-encrypting or re-entering those secrets.
- **Storage:** hospital logos live on the `public` disk (`php artisan storage:link`). Uploaded evaluation files stay in Typeform and are proxied on demand.
- **Monitoring:** watch the queue (`php artisan queue:failed`), `Admin → Overview` alerts (webhook problems, failed deliveries, mapping review) and `Admin → Deliveries`.

See [`docs/RUNBOOK.md`](docs/RUNBOOK.md) for operational procedures and [`docs/DEPLOY-SITEGROUND.md`](docs/DEPLOY-SITEGROUND.md) for shared hosting without Docker/Redis (SiteGround).
