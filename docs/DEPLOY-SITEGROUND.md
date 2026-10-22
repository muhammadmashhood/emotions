# Deploying to SiteGround

SiteGround shared/cloud hosting has no Docker, no Redis and no always-on processes, but it has everything this app needs:
PHP 8.3, MySQL, cron jobs, SSH, free HTTPS. This guide deploys the app with **database-backed sessions, cache and queue**,
driven by two cron jobs. Webhooks are processed within about a minute instead of seconds (the cron cadence).

Verified locally in this mode: webhook → `jobs` table → `queue:work database --stop-when-empty` → scored evaluation, cache locks OK.

## 0. Before you start

- A SiteGround plan with **SSH** (GrowBig or higher) and a domain or subdomain for the app, e.g. `evaluations.yourdomain.com`, with **Let's Encrypt** enabled in Site Tools → Security → SSL Manager.
- Site Tools → Devs → **PHP Manager** → set PHP **8.3** for that site.
- Site Tools → Site → **MySQL** → create a database + user (all privileges). Note the names (they are prefixed with your account id).
- Site Tools → Email → Accounts → create `no-reply@yourdomain` and note the SMTP host/port.
- SSH access: Site Tools → Devs → SSH Keys Manager → add your public key; note user/host/port (SiteGround uses port **18765**).

## 1. Build the release bundle (on your machine)

SiteGround has no Node and you should not run Composer there, so the bundle ships `vendor/` and `public/build/`:

```bash
./deploy/build-release.sh
# → release/emotion-release-YYYYmmdd-HHMM.zip  (committed files + vendor + built assets, no tests/docker/.env)
```

## 2. Upload and unpack

```bash
scp -P 18765 release/emotion-release-*.zip USER@HOST:~/
ssh -p 18765 USER@HOST
mkdir -p ~/www/evaluations.yourdomain.com/emotion && cd ~/www/evaluations.yourdomain.com/emotion
unzip -oq ~/emotion-release-*.zip && rm ~/emotion-release-*.zip
```

(Site Tools → File Manager also works for uploading and extracting the zip.)

## 3. Make only `public/` web-accessible

Laravel must be served from its `public` folder: everything else (`.env` with the database password and `APP_KEY`, `storage/logs`,
`vendor`, …) must stay outside the web root. Pick one of the two layouts.

**Option A (recommended): app next to `public_html`, symlink the web root.** One command, updates never touch it:

```bash
cd ~/www/evaluations.yourdomain.com
mv public_html public_html.bak          # or rm -rf if empty
ln -s "$PWD/emotion/public" public_html
```

**Option B: everything inside `public_html`, guarded by a root `.htaccess`.** Unzip the bundle directly into `public_html`
and copy `root.htaccess` (in the bundle) to `public_html/.htaccess`. It forwards every request into `public/` and returns
403 for the application folders and files. Verify after deploying that `https://yourdomain/.env` and `https://yourdomain/storage/logs/`
return 403, not content. Option B depends on that one file staying in place; Option A cannot expose anything even if it is removed.

In both cases `public/.htaccess` (shipped) rewrites to `index.php`. Site Tools → Speed → Caching: exclude the site from dynamic cache (pages are per-user).

## 4. Configure

```bash
cd ~/www/evaluations.yourdomain.com/emotion
cp .env.siteground.example .env
nano .env       # APP_URL, DB_*, MAIL_* from Site Tools
php artisan key:generate --force
chmod -R ug+rwx storage bootstrap/cache
```

**Back up `APP_KEY`** (password manager). It encrypts the Typeform token and webhook secrets.

## 5. Migrate and warm up

```bash
./post-deploy.sh                       # migrate --force, storage:link, config/route/view caches
php artisan app:create-super-admin     # first admin account
php artisan about                      # sanity check: environment production, drivers database
```

Do **not** run `db:seed` in production (the demo seeder refuses anyway).

## 6. Cron jobs (Site Tools → Devs → Cron Jobs)

Add the two lines from `cron.txt`, replacing `APP` with the absolute path (`pwd` prints it):

| Interval | Command |
|---|---|
| every minute | `cd APP && php artisan schedule:run >> /dev/null 2>&1` |
| every minute | `cd APP && php artisan queue:work database --queue=webhooks,default --stop-when-empty --max-time=50 --tries=3 --backoff=10,60,300 >> /dev/null 2>&1` |

If `php` on the CLI is not 8.3 (`php -v`), use the versioned binary SiteGround provides (e.g. `/usr/local/php83/bin/php`) in both lines.

## 7. Connect Typeform and link a form

1. Log in → **Settings → Typeform** → paste a personal access token (scopes `accounts:read forms:read workspaces:read webhooks:read webhooks:write responses:read`).
2. **Hospitals → New hospital → Link a form.** The webhook is registered at `https://evaluations.yourdomain.com/webhooks/typeform/{uuid}`; Typeform requires HTTPS, which Let's Encrypt provides.
3. Submit the form in Typeform; **Admin → Deliveries** shows the request, and the evaluation appears once the next queue cron run processes it (≤ 1 min).

## 8. Continuous deployment from GitHub

SiteGround cannot pull from GitHub, so GitHub pushes to SiteGround instead: a GitHub Actions workflow builds the app
(Composer + Node) and uploads the result. Nothing has to be installed on the server.

### 8a. Over SSH (recommended — runs migrations and caches for you)

Workflow: `.github/workflows/deploy-siteground-ssh.yml`, runs on every push to `master` and manually.

1. Generate a deploy key on your computer: `ssh-keygen -t ed25519 -f sg_deploy -N "" -C "github-deploy"`. This gives `sg_deploy` (private) and `sg_deploy.pub` (public).
2. Site Tools → Devs → **SSH Keys Manager** → *Import* → paste the content of `sg_deploy.pub`. Note the SSH **username**, **host** and **port** shown there (SiteGround uses port 18765).
3. GitHub → repo → Settings → Secrets and variables → Actions → add:

   | Secret | Value |
   |---|---|
   | `SG_SSH_HOST` | the host from SSH Keys Manager (e.g. `bilalj19.sg-host.com` or an IP) |
   | `SG_SSH_PORT` | `18765` |
   | `SG_SSH_USER` | the SSH username shown there |
   | `SG_SSH_KEY` | the full content of the **private** key file `sg_deploy` |
   | `SG_APP_DIR` | absolute app folder, e.g. `/home/customer/www/bilalj19.sg-host.com/public_html` |
   | `SG_PHP` (optional) | PHP binary if plain `php` is old, e.g. `/usr/local/php83/bin/php` |

4. Push to `master` or run the workflow from the Actions tab. It rsyncs only changed files (never `.env`, `storage/`, the root `.htaccess`)
   and then runs `post-deploy.sh` on the server: `migrate --force`, `storage:link`, config/route/view caches, `queue:restart`.
5. Because the caches are now built on each deploy, after editing `.env` by hand run the workflow again (or `php artisan config:cache` over SSH).

With SSH available you can also run any artisan command directly (`php artisan app:create-super-admin`, `evaluations:rescore`, …);
see the RUNBOOK.

### 8b. Over FTPS (fallback for plans without SSH)

Workflow: `.github/workflows/deploy-siteground.yml`, manual only (Actions → *Deploy to SiteGround (FTP fallback)* → Run workflow).

1. Site Tools → Site → **FTP Accounts** → create an account. Set its *Path* to the site root (e.g. `www/bilalj19.sg-host.com`)
   so the uploads land in `public_html/`. Note the hostname shown (usually your domain or `ftp.yourdomain`), username and password.
2. GitHub → repository → Settings → Secrets and variables → Actions → **New repository secret**, four times:

   | Secret | Value |
   |---|---|
   | `SG_FTP_SERVER` | the FTP hostname from Site Tools |
   | `SG_FTP_USERNAME` | the FTP username (looks like `user@yourdomain`) |
   | `SG_FTP_PASSWORD` | its password |
   | `SG_FTP_DIR` | `public_html/` (or `emotion/` if you chose the symlink layout) |

3. Push to `master` (or Actions → *Deploy to SiteGround* → *Run workflow*). The first run uploads everything (several minutes);
   later runs upload only changed files, tracked in `.ftp-deploy-sync-state.json` on the server.
4. The workflow never touches `.env`, `storage/` or the root `.htaccess`, so your configuration and data survive every deploy.
5. When a release adds database migrations, run them once with a temporary cron job (Site Tools → Cron Jobs):
   `php /home/customer/www/yourdomain/public_html/artisan migrate --force >> /home/customer/www/yourdomain/public_html/storage/logs/migrate.log 2>&1`
   — or keep that line as a daily job; it does nothing when there is nothing to migrate.

## 9. Updating by hand (alternative)

```bash
./deploy/build-release.sh            # locally
scp -P 18765 release/emotion-release-*.zip USER@HOST:~/
ssh -p 18765 USER@HOST 'cd ~/www/evaluations.yourdomain.com/emotion && unzip -oq ~/emotion-release-*.zip && rm ~/emotion-release-*.zip && ./post-deploy.sh'
```

`.env`, `storage/` and uploaded logos are never inside the zip, so they survive updates.

## Troubleshooting

| Symptom | Check |
|---|---|
| 500 on every page | `storage/logs/laravel-*.log`; usually `APP_KEY` missing or `storage/` not writable |
| Login page loads without styling | `public/build/` missing → rebuild the bundle (`npm run build` runs inside `build-release.sh`) |
| Deliveries stay "Pending" | queue cron not running, or wrong `php` binary in the cron line; run the queue command by hand over SSH |
| Webhook "Error" after linking | `APP_URL` not HTTPS/public, or token lacks `webhooks:write`; fix and click **Repair webhook** |
| Emails not sent | `MAIL_*` from Site Tools; test with `php artisan tinker --execute="Mail::raw('hi', fn($m) => $m->to('you@example.com')->subject('test'));"` |
| Slow dashboard | `php artisan optimize` after each deploy; the heavy queries use covering indexes already |
