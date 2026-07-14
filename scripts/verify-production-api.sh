#!/usr/bin/env bash
set -euo pipefail

DOMAIN="${1:-demo-marketplace.local}"
APP_PATH="${2:-/var/www/demo-marketplace}"

echo "== API verification for ${DOMAIN} =="
echo "App path: ${APP_PATH}"

if [ ! -f "${APP_PATH}/artisan" ]; then
  echo "ERROR: artisan not found at ${APP_PATH}/artisan"
  exit 1
fi

cd "${APP_PATH}"

echo
echo "[1/5] Laravel API routes"
php artisan route:list --path=api/v1 || true

echo
echo "[2/5] Clear and rebuild caches"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache

echo
echo "[3/5] Public API endpoint checks"
for ep in /api/v1/home /api/v1/categories /api/v1/listings; do
  echo "- GET https://${DOMAIN}${ep}"
  curl -sS -L -o /tmp/demo_api_probe.json -w "status=%{http_code}\n" "https://${DOMAIN}${ep}"
  head -c 220 /tmp/demo_api_probe.json || true
  echo
done

echo
echo "[4/5] Auth-protected endpoint check"
echo "- GET https://${DOMAIN}/api/v1/auth/me (expected 401 without token)"
curl -sS -L -o /tmp/demo_api_auth_probe.json -w "status=%{http_code}\n" "https://${DOMAIN}/api/v1/auth/me"
head -c 220 /tmp/demo_api_auth_probe.json || true
echo

echo
echo "[5/5] Nginx config test"
sudo nginx -t

echo
echo "Verification complete. If status codes are 200 for public endpoints and 401 for /auth/me, API routing is healthy."
