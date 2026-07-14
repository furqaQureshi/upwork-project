# Unisell Mobile Marketplace (Laravel PWA + TWA)

Full-featured Unisell-style marketplace built with Laravel, Blade, Tailwind, PWA support, and a custom admin panel.

## Features

- Mobile-first marketplace UI with app-like navigation.
- Authentication and user profile with city/state/phone.
- Listing creation, editing, photo upload, moderation workflow.
- Search and filter by keyword, category, city, condition, and price range.
- Favorites and real-time-like buyer/seller chat flow.
- Paid featured ad boosting with Razorpay, PhonePe, and Paytm support.
- In-app push-style notifications for:
	- New incoming chat messages.
	- Listing approved/rejected updates.
	- Compact animated notifications drawer + sound cue for new messages.
- Full Web Push support (VAPID + browser subscriptions) for background notifications when app is closed.
- Admin panel for:
	- Dashboard analytics.
	- Listing approval/rejection/featured toggles.
	- Category management.
	- User role and block controls.
	- Listing reports moderation.
- PWA support:
	- Web manifest.
	- Service worker caching and offline fallback.
	- Install prompt hooks.
- TWA-ready Android packaging files.

## Tech Stack

- Laravel 12
- PHP 8.2+
- Blade + Tailwind CSS + Alpine.js
- SQLite (default), easy to switch to MySQL/PostgreSQL

## Quick Start

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
npm run build
php artisan serve
# Optional but recommended to process queued notifications in local dev:
php artisan queue:work
```

Open: `http://127.0.0.1:8000`

## Seeded Accounts

- Admin
	- Email: `admin@unisellmobile.test`
	- Password: `Admin@12345`
- Seller
	- Email: `seller@unisellmobile.test`
	- Password: `Seller@12345`
- User
	- Email: `test@example.com`
	- Password: `User@12345`

## PWA Notes

- Manifest: `public/manifest.webmanifest`
- Service worker: `public/sw.js`
- Offline page: `resources/views/offline.blade.php`
- Icons: `public/icons/`

## TWA Notes

- TWA config template: `twa/twa-manifest.json`
- Packaging guide: `twa/README.md`
- Digital asset links template: `public/.well-known/assetlinks.json`

Before Play Store release, replace placeholder package ID, host, and certificate fingerprints.

## Featured Payment Setup

Set gateway credentials in `.env`:

```env
FEATURED_AD_CURRENCY=INR
FEATURED_AD_DAILY_RATE=49

RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=

PHONEPE_MERCHANT_ID=
PHONEPE_SALT_KEY=
PHONEPE_SALT_INDEX=1

PAYTM_MID=
PAYTM_MERCHANT_KEY=
PAYTM_WEBSITE=WEBSTAGING
```

If credentials are missing, the checkout automatically falls back to a mock completion flow so feature promotion can still be tested end-to-end.

## Push Notification Flow

- Server side:
	- Laravel database notifications (`notifications` table) for in-app drawer.
	- Web Push delivery using `minishlink/web-push` and user subscription storage.
- Client side:
	- Compact animated notifications drawer with unread badges.
	- Sound cue for new incoming message notifications when app is open.
	- Push API subscription sync + service worker `push` event for true background alerts.
- Trigger points:
	- New message sent in chat.
	- Admin approve/reject action on listing.

### Web Push Setup (VAPID)

1. Generate VAPID keys:

```bash
npx web-push generate-vapid-keys
```

2. Add generated keys to `.env`:

```env
WEB_PUSH_VAPID_SUBJECT=mailto:admin@example.com
WEB_PUSH_VAPID_PUBLIC_KEY=...
WEB_PUSH_VAPID_PRIVATE_KEY=...
WEB_PUSH_TTL=43200
OPENAI_API_KEY=...
```

3. Run migrations to create push subscriptions table:

```bash
php artisan migrate
```

4. Ensure HTTPS is enabled in production (required for Push API).

Once users grant notification permission, browser subscriptions are saved automatically and used for background push even when the app is fully closed.

## One-Command Production Deployment

Use this command on your Linux server:

```bash
sudo DOMAIN=market.example.com APP_PATH=/var/www/unisell-new APP_USER=www-data PHP_VERSION=8.2 SSL_EMAIL=ops@example.com bash scripts/deploy-production.sh
```

It configures Nginx, SSL, queue worker, scheduler cron, and Laravel caches in one run.

Detailed guide: `deploy/README.md`

## AI SEO Automation

- New command: `php artisan seo:ai-optimize`
- Force run (ignores interval): `php artisan seo:ai-optimize --force`
- Scheduler is pre-wired in `routes/console.php` to run every 5 minutes, and the command respects configured interval (`ai_seo_audit_interval_minutes`).
- Public SEO endpoints:
	- `GET /sitemap.xml`
	- `GET /robots.txt`
