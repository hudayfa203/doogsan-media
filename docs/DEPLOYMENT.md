# Deploying Doogsan Media

This guide covers the current production situation for **doogsanmedia.com**:

- Domain at **Namecheap**, nameservers `dns1/dns2.namecheaphosting.com`, web server **184.94.213.226** (Namecheap shared hosting, cPanel).
- The domain currently serves a **live WordPress site (JNews theme)** with real articles.
- Email is delivered through Google Workspace and Namecheap mail (MX records). **Do not change DNS** — the Laravel app moves in alongside on the same hosting.

> Nothing in this guide replaces the live WordPress site until the **Cut-over** step, which must be approved by Doogsan Media management.

---

## 1. Hosting capability check (Namecheap shared hosting)

| Requirement | Shared hosting | Notes |
|---|---|---|
| PHP 8.3+ with `pdo_mysql, mbstring, intl, gd, zip, fileinfo, openssl` | ✅ via cPanel → **Select PHP Version** | Choose 8.3 or 8.4 and tick the extensions |
| MySQL / MariaDB | ✅ cPanel → **MySQL Databases** | |
| Composer | ✅ over SSH (`composer -V`) | if missing: `curl -sS https://getcomposer.org/installer \| php` |
| SSH | ✅ port **21098**, enable in cPanel → **Manage Shell** | |
| Cron | ✅ minimum interval **5 minutes** | scheduled publishing fires within ≤ 5 min |
| Queue worker / Reverb WebSocket daemon | ❌ long-running processes are not allowed | use cron-driven queue + polling or a hosted push service (below) |
| Free SSL (AutoSSL / Let’s Encrypt) | ✅ cPanel → **SSL/TLS Status** | |

**Real-time on shared hosting.** Two options, both supported by the code:

1. **Polling (default, no extra cost):** set `BROADCAST_CONNECTION=null`. Browsers check `/api/{edition}/pulse` every `DOOGSAN_POLL_INTERVAL` seconds (20–45 s recommended). Breaking news and new stories appear without refreshing, with a short delay.
2. **Hosted push (instant):** a Pusher-compatible service (e.g. Pusher Channels). Laravel sends events over HTTPS, so no daemon is needed. Set `BROADCAST_CONNECTION=pusher` and the `PUSHER_*` variables. Check the plan's concurrent-connection limit against your audience.

For self-hosted Reverb + Redis + a permanent queue worker, use a **VPS** (section 7).

---

## 2. Prepare cPanel (once)

1. **PHP:** cPanel → Select PHP Version → 8.3+, extensions listed above.
2. **Database:** cPanel → MySQL Databases → create `USER_doogsan` database and user, grant **ALL PRIVILEGES**. Keep the password for `.env` only.
3. **Subdomain for staging:** cPanel → Domains → create `new.doogsanmedia.com` with document root `/home/USER/doogsan/current/public`.
4. **SSH:** enable *Manage Shell*. Create a deploy key: on your computer run `ssh-keygen -t ed25519 -f doogsan_deploy`, paste `doogsan_deploy.pub` into cPanel → SSH Access → Manage SSH Keys → Import, then **Authorize** it.

## 3. GitHub Actions deployment

The workflow in `.github/workflows/deploy.yml` runs on every push to `main` (and manually). It:

1. installs Composer dependencies (no dev) and builds assets on GitHub’s runners,
2. uploads a release to `~/doogsan/releases/<timestamp>` over SSH (port 21098),
3. links the shared `.env` and `storage`, runs `php artisan migrate --force`, caches config/routes/views,
4. switches the `~/doogsan/current` symlink atomically and keeps the last 5 releases for rollback.

Add these **repository secrets** (GitHub → Settings → Secrets and variables → Actions). They never appear in the code:

| Secret | Value |
|---|---|
| `DEPLOY_HOST` | `server` hostname from cPanel (or 184.94.213.226) |
| `DEPLOY_PORT` | `21098` |
| `DEPLOY_USER` | your cPanel username |
| `DEPLOY_KEY` | contents of the private key `doogsan_deploy` |
| `DEPLOY_PATH` | `/home/USER/doogsan` |

## 4. First deployment to staging (`new.doogsanmedia.com`)

Over SSH, once:

```bash
mkdir -p ~/doogsan/shared/storage/{app/public,framework/{cache/data,sessions,views},logs} ~/doogsan/releases
nano ~/doogsan/shared/.env        # copy .env.example and fill in (see below)
```

Production `.env` essentials:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://new.doogsanmedia.com        # later https://doogsanmedia.com
APP_KEY=                                    # php artisan key:generate --show
DB_CONNECTION=mysql
DB_DATABASE=USER_doogsan
DB_USERNAME=USER_doogsan
DB_PASSWORD=********
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
BROADCAST_CONNECTION=null                   # or pusher
DOOGSAN_POLL_INTERVAL=25
DOOGSAN_ENFORCE_2FA=true
MAIL_MAILER=smtp                            # info@doogsanmedia.com mailbox credentials
ADMIN_EMAIL=info@doogsanmedia.com
```

Then trigger the workflow (Actions → Deploy → Run workflow). After the first deploy, over SSH:

```bash
cd ~/doogsan/current
php artisan db:seed --force          # roles, sections, pages, menus, settings + prints one-time admin password
php artisan storage:link
```

**Cron** (cPanel → Cron Jobs, every 5 minutes):

```
*/5 * * * * cd /home/USER/doogsan/current && php artisan schedule:run >> /dev/null 2>&1
*/5 * * * * cd /home/USER/doogsan/current && php artisan queue:work --stop-when-empty --max-time=240 >> /dev/null 2>&1
```

## 5. Import the existing WordPress articles

```bash
cd ~/doogsan/current
php artisan doogsan:import-wordpress https://doogsanmedia.com --dry-run   # report only
php artisan doogsan:import-wordpress https://doogsanmedia.com             # import
```

Each post is filed into the Somali or English newsroom by language detection (editors can move any mis-filed story). Authors, categories, tags and featured images are imported, and a 301 redirect is created from every old WordPress URL to its new article URL.

## 6. Cut-over (requires approval)

1. **Back up WordPress:** cPanel → Backup → download a full home-directory backup **and** the WordPress database (phpMyAdmin → Export). Keep both off the server.
2. Move WordPress aside: `mv ~/public_html ~/public_html_wordpress`
3. Point the main domain at Laravel: `ln -s ~/doogsan/current/public ~/public_html`
4. Update `APP_URL=https://doogsanmedia.com` in `~/doogsan/shared/.env`, then `php artisan config:cache`.
5. Run a final `doogsan:import-wordpress` to catch posts published since staging.
6. Verify `/`, `/en`, `/so`, `/admin`, an old WordPress URL (should 301), sitemaps and HTTPS.

**Rollback (minutes):** `rm ~/public_html && mv ~/public_html_wordpress ~/public_html`.

## 7. VPS alternative (full real-time)

Ubuntu 24.04, Nginx, PHP 8.3-FPM, MariaDB, Redis, Supervisor. Supervisor programs: `php artisan queue:work redis` and `php artisan reverb:start`; Nginx proxies `/app` and `/apps` to Reverb on port 8080. Set `BROADCAST_CONNECTION=reverb`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`.

## 8. Operations

- **Backups:** daily database backup at 01:30 (`storage/app/backups`, retention configurable in Admin → Backups). Also enable cPanel/JetBackup and download weekly copies off-server.
- **Health:** Admin → System Health shows scheduler heartbeat, queue, storage, broadcasting and stream checks.
- **Sample content:** never run `doogsan:sample` on production after launch; if it was used on staging, run `php artisan doogsan:sample --remove`.
- **Troubleshooting:** `storage/logs/laravel.log`; `php artisan about`; clear caches with `php artisan optimize:clear`.
