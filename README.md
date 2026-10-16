# eMotion Evaluations

Multi-tenant platform that turns Typeform mystery-shopping surveys into scored dashboards for hospitals.
Built with PHP 8.3 / Laravel 11, Livewire 3, Tailwind, MySQL 8 and Redis. See `SPEC.md` for the full specification,
`docs/DECISIONS.md` for recorded decisions, `docs/PROGRESS.md` for milestone status and `docs/RUNBOOK.md` for operations.

## Requirements

- Docker + Docker Compose (recommended), or PHP 8.2+, Composer, Node 20, MySQL 8, Redis for a native setup.
- A public HTTPS URL (ngrok in development) so Typeform can reach the webhook endpoint.

## Quick start (Docker)

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed       # demo data: super admin, 2 hospitals, forms, evaluations
open http://localhost:8080/login
```

Default local super admin: `admin@example.com` / `change-me` (from `SUPER_ADMIN_EMAIL` / `SUPER_ADMIN_PASSWORD`, local only).
Mailpit (invitation emails) is at http://localhost:8025.

## Native development

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# point DB_* / REDIS_* at your local services, then:
php artisan migrate --seed
npm run dev          # Vite
php artisan serve    # http://localhost:8000
php artisan queue:work redis --queue=webhooks,default
php artisan schedule:work
```

## Tests and static analysis

Tests run against MySQL (to match production JSON behaviour). Start a throwaway MySQL on port 3307:

```bash
docker run -d --name emotion-test-mysql -e MYSQL_ROOT_PASSWORD=secret -e MYSQL_DATABASE=emotion_test \
  -p 127.0.0.1:3307:3306 mysql:8.0
./vendor/bin/pest                 # full suite
./vendor/bin/pest --coverage      # with coverage (needs xdebug/pcov)
./vendor/bin/phpstan analyse      # Larastan level 6
./vendor/bin/pint --test          # formatting check
```

## Webhooks in development (ngrok)

Typeform only delivers webhooks to public HTTPS URLs.

```bash
ngrok http 8080
# set APP_URL=https://<your-id>.ngrok-free.app in .env, then restart the containers
```

Forms linked before the URL change can be repaired from Admin → Forms → "Repair webhook".

## Linking a real form

1. Log in as super admin → Admin → Settings → Typeform. Enter a personal access token with scopes
   `accounts:read forms:read workspaces:read webhooks:read webhooks:write responses:read`. The token is stored encrypted.
2. Admin → Hospitals → create a hospital → Forms tab → "Link a form" → pick the Typeform form.
3. The platform fetches the structure, maps sections, registers the webhook and backfills existing responses.
4. Review the mapping (fix misplaced questions, rename sections), save, and optionally recalculate scores.
