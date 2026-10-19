# Doogsan Media

Bilingual news platform for **[doogsanmedia.com](https://doogsanmedia.com)** — two independent publications (English at `/en`, Somali at `/so`) with a shared newsroom CMS at `/admin`.

Built with **Laravel 13** (PHP 8.3+), Blade + Alpine.js, Tailwind CSS 4, TipTap, Laravel Reverb.

Designed & Developed by [Somali Digital](https://somalidigital.so).

---

## What's inside

**Public site** (`/en`, `/so`)
- Separate English and Somali editions — own homepage, stories, sections, slugs, breaking ticker, live coverage, videos and SEO. A story appears in the other edition only if an editor deliberately creates and approves a version for it.
- Editorial homepage driven by the Homepage Builder; breaking-news ticker and sitewide urgent banner; live blogs; Live TV (HLS / YouTube / external) with schedule and honest off-air state; video, documentaries, podcasts, photo galleries.
- Article pages: standfirst, bylines, corrections/updates log, reading time, in-article contents, galleries with lightbox, pull quotes, fact boxes, embeds, share/copy/print, related coverage, audio narration.
- Search (keyword, edition, section, journalist, format, dates, sort), archive, journalist profiles, team page, contact, encrypted news-tip form, newsletter with double opt-in.
- SEO: canonical + hreflang, Open Graph, `NewsArticle` / `LiveBlogPosting` / `VideoObject` / breadcrumb JSON-LD, per-edition XML + Google News sitemaps, RSS per edition, redirects manager, robots controls.
- Live clock in Africa/Mogadishu; timestamps stored in UTC; relative times update in the browser.
- Real-time via Laravel Reverb (WebSockets). When sockets are unavailable (e.g. shared hosting) the site falls back to polling `/api/{edition}/pulse` — no fake "live" states.
- Light/dark mode, PWA manifest + offline page, mobile-first layout tested at 360 px.

**Newsroom** (`/admin`)
- Executive overview + **Somali Newsroom** and **English Newsroom** workspaces.
- Workflow per story: Draft → Submitted → Editing → Approved → Scheduled/Published → Archived, with permission checks, publishing checklist, autosave, edit locks, revision history with diff and rollback, correction notices, internal notes, assignments and deadlines, scheduled publish/unpublish.
- Breaking News Control, Live Coverage desk, Homepage Builder (schedulable), Media Library (responsive WebP variants, credits/alt text), Live TV Studio, Video & Podcasts, Journalists, Sections & Tags, Pages, Navigation, SEO Center, Advertising, Analytics (first-party, cookieless), Newsletter, Messages & Tips, Users, Roles & Permissions (11 newsroom roles), Settings (branding per edition, social links, contact details), Audit Log, System Health, Backups.
- TOTP two-factor authentication (enforceable per role), rate-limited sign-in, audit trail.

## Quick start (local)

```bash
cp .env.example .env
composer install
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed            # prints a one-time admin password
npm install && npm run build
php artisan serve                     # http://127.0.0.1:8000
php artisan reverb:start              # optional: real-time WebSockets
```

### Sample content (design review only)

```bash
php artisan doogsan:sample            # installs labelled sample stories, journalists, live blogs, videos
php artisan doogsan:sample --remove   # deletes exactly those records before launch
```

Everything installed this way carries a **Sample / Tusaale** badge and is tracked, so removal never touches real newsroom content. Never run it on the production database after launch.

## Production

See **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)** for Namecheap (cPanel/shared hosting) and VPS deployment, the GitHub Actions workflow, cron/queue setup, HTTPS, backups and the safe cut-over from the existing WordPress site.

Never commit `.env`, database dumps or backups. Secrets live in the server's `.env` and in GitHub Actions secrets.

## Tests

```bash
php artisan test
```

## Project layout

| Path | Purpose |
|---|---|
| `app/Http/Controllers/Site` | Public pages for both editions |
| `app/Http/Controllers/Admin` | Newsroom CMS |
| `app/Services` | Workflow, article saving, body rendering/sanitising, homepage engine, search, analytics, media, 2FA, backups, health, sample content |
| `config/doogsan.php` | Editions, localized URL segments, formats, workflow |
| `routes/web.php`, `routes/admin.php`, `routes/api.php`, `routes/console.php` | Routes and scheduled jobs |
| `resources/views/site`, `resources/views/admin` | Blade templates |
| `resources/css`, `resources/js` | Design systems and front-end behaviour |
| `lang/so.json`, `lang/{en,so}/*.php` | Interface translations |
| `database/samples` | Sample content kit (review only) |
