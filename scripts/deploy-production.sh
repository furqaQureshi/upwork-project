#!/usr/bin/env bash
set -euo pipefail

DOMAIN="${1:-${DOMAIN:-}}"
APP_PATH="${APP_PATH:-$(cd "$(dirname "$0")/.." && pwd)}"
APP_USER="${APP_USER:-www-data}"
PHP_VERSION="${PHP_VERSION:-8.2}"
SSL_EMAIL="${SSL_EMAIL:-}"
SKIP_SSL="${SKIP_SSL:-0}"

if [[ -z "${DOMAIN}" ]]; then
    echo "Usage: sudo DOMAIN=demo-marketplace.local SSL_EMAIL=ops@example.com bash scripts/deploy-production.sh"
    echo "Or pass domain as first argument: sudo bash scripts/deploy-production.sh demo-marketplace.local"
    exit 1
fi

if [[ "${EUID}" -ne 0 ]]; then
    echo "This script must run as root (use sudo)."
    exit 1
fi

if [[ ! -f "${APP_PATH}/artisan" ]]; then
    echo "Could not find artisan at APP_PATH=${APP_PATH}."
    exit 1
fi

if ! id "${APP_USER}" >/dev/null 2>&1; then
    echo "APP_USER '${APP_USER}' does not exist on this server."
    exit 1
fi

if [[ -z "${SSL_EMAIL}" ]]; then
    SSL_EMAIL="admin@${DOMAIN}"
fi

run_as_app_user() {
    runuser -u "${APP_USER}" -- bash -lc "cd '${APP_PATH}' ; $*"
}

echo "==> Installing system dependencies"
apt-get update
apt-get install -y nginx certbot python3-certbot-nginx cron git curl unzip \
    "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-mbstring" \
    "php${PHP_VERSION}-xml" "php${PHP_VERSION}-curl" "php${PHP_VERSION}-sqlite3" \
    "php${PHP_VERSION}-zip" "php${PHP_VERSION}-bcmath" "php${PHP_VERSION}-intl"

if ! command -v composer >/dev/null 2>&1; then
    echo "==> Installing Composer"
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

if ! command -v npm >/dev/null 2>&1; then
    echo "==> Installing Node.js and npm"
    apt-get install -y nodejs npm
fi

echo "==> Preparing writable directories"
mkdir -p "${APP_PATH}/storage" "${APP_PATH}/bootstrap/cache"
chown -R "${APP_USER}:${APP_USER}" "${APP_PATH}/storage" "${APP_PATH}/bootstrap/cache"

if [[ ! -f "${APP_PATH}/.env" && -f "${APP_PATH}/.env.example" ]]; then
    echo "==> Creating .env from .env.example"
    cp "${APP_PATH}/.env.example" "${APP_PATH}/.env"
    chown "${APP_USER}:${APP_USER}" "${APP_PATH}/.env"
fi

echo "==> Installing app dependencies"
run_as_app_user "composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction"
run_as_app_user "npm ci"
run_as_app_user "npm run build"

echo "==> Running Laravel production commands"
run_as_app_user "php artisan storage:link || true"
run_as_app_user "php artisan migrate --force"
run_as_app_user "php artisan config:clear"
run_as_app_user "php artisan cache:clear"
run_as_app_user "php artisan route:cache"
run_as_app_user "php artisan config:cache"
run_as_app_user "php artisan view:cache"

NGINX_CONF="/etc/nginx/sites-available/${DOMAIN}.conf"

echo "==> Writing Nginx configuration"
cat > "${NGINX_CONF}" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN} www.${DOMAIN};

    root ${APP_PATH}/public;
    index index.php index.html;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

ln -sfn "${NGINX_CONF}" "/etc/nginx/sites-enabled/${DOMAIN}.conf"
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx
systemctl enable nginx
systemctl enable "php${PHP_VERSION}-fpm"
systemctl restart "php${PHP_VERSION}-fpm"

if [[ "${SKIP_SSL}" != "1" ]]; then
    echo "==> Requesting SSL certificate with Certbot"
    certbot --nginx -d "${DOMAIN}" -d "www.${DOMAIN}" --non-interactive --agree-tos -m "${SSL_EMAIL}" --redirect
else
    echo "==> SKIP_SSL=1, skipping SSL issuance"
fi

QUEUE_SERVICE="unisell-new-queue.service"

echo "==> Configuring queue worker service"
cat > "/etc/systemd/system/${QUEUE_SERVICE}" <<EOF
[Unit]
Description=Unisell New Laravel Queue Worker
After=network.target

[Service]
User=${APP_USER}
Group=${APP_USER}
Restart=always
RestartSec=5
WorkingDirectory=${APP_PATH}
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable "${QUEUE_SERVICE}"
systemctl restart "${QUEUE_SERVICE}"

echo "==> Ensuring scheduler cron entry"
SCHEDULE_CMD="* * * * * cd ${APP_PATH} && /usr/bin/php artisan schedule:run >> /dev/null 2>&1"
EXISTING_CRON="$(crontab -u "${APP_USER}" -l 2>/dev/null || true)"
if ! grep -Fq "artisan schedule:run" <<< "${EXISTING_CRON}"; then
    {
        echo "${EXISTING_CRON}"
        echo "${SCHEDULE_CMD}"
    } | crontab -u "${APP_USER}" -
fi
systemctl enable cron
systemctl restart cron

echo "==> Restarting queue workers and warming cache"
run_as_app_user "php artisan queue:restart"
run_as_app_user "php artisan optimize"

echo ""
echo "Deployment complete"
echo "Domain: ${DOMAIN}"
echo "App path: ${APP_PATH}"
echo "Queue service: ${QUEUE_SERVICE}"
echo "SSL email: ${SSL_EMAIL}"
echo ""
echo "Checklist status:"
echo "[x] Nginx configured"
echo "[x] SSL issued (unless SKIP_SSL=1)"
echo "[x] Queue worker service enabled"
echo "[x] Scheduler cron configured"
echo "[x] Laravel cache optimized"
