# Production Deployment (One Command)

Run this on an Ubuntu/Debian server after the project code is present in your target directory.

```bash
sudo DOMAIN=market.example.com APP_PATH=/var/www/unisell-new APP_USER=www-data PHP_VERSION=8.2 SSL_EMAIL=ops@example.com bash scripts/deploy-production.sh
```

## What the command configures

- Nginx virtual host for your domain.
- Let's Encrypt SSL via Certbot with auto redirect to HTTPS.
- Queue worker systemd service (`unisell-new-queue.service`).
- Scheduler cron entry (`php artisan schedule:run` every minute).
- Laravel production optimization and cache commands.
- Composer + npm dependency install and asset build.

## Optional environment flags

- `SKIP_SSL=1` to skip certificate issuance during dry runs.
- `APP_PATH` to point to a non-default deployment path.
- `APP_USER` to run queue/cron under a specific Linux user.
- `PHP_VERSION` when your server uses a version other than `8.2`.

## Safety notes

- Run with root privileges (`sudo`) because Nginx/systemd/cron are configured.
- Ensure DNS records for `DOMAIN` and `www.DOMAIN` point to your server before SSL issuance.
- For first deployment, edit `.env` production values (APP_ENV, APP_DEBUG, DB settings, payment keys).

## API 404 Fix (unisell.online) Step By Step

If web pages load but `/api/v1/*` returns 404 in production, follow these steps exactly.

1. Update `.env` URL:

```bash
cd /var/www/unisell-new
sed -i 's#^APP_URL=.*#APP_URL=https://unisell.online#' .env
```

2. Ensure Nginx forwards all routes to Laravel:

```bash
sudo cp deploy/nginx-unisell.online.conf /etc/nginx/sites-available/unisell.online
sudo ln -sf /etc/nginx/sites-available/unisell.online /etc/nginx/sites-enabled/unisell.online
sudo nginx -t
sudo systemctl reload nginx
```

3. Rebuild Laravel runtime caches:

```bash
cd /var/www/unisell-new
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
```

4. Verify API routes are registered:

```bash
php artisan route:list --path=api/v1
```

5. Verify endpoint responses:

```bash
bash scripts/verify-production-api.sh unisell.online /var/www/unisell-new
```

Expected results:
- `/api/v1/home`, `/api/v1/categories`, `/api/v1/listings` return `200`.
- `/api/v1/auth/me` returns `401` without token.
