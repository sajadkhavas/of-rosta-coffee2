#!/usr/bin/env bash
set -Eeuo pipefail

EXPECTED_RELEASE_SHA="4a54780d504b91527a86777e7f04368022354686"
OUTPUT_DIR="${ROSTA_SERVER_READY_DIR:-.server-ready}"

site_domain="${1:-}"
acme_email="${2:-}"

[[ -n "$site_domain" ]] || {
  echo "Usage: $0 <staging-site-domain> <acme-email>" >&2
  exit 2
}
[[ -n "$acme_email" ]] || {
  echo "Usage: $0 <staging-site-domain> <acme-email>" >&2
  exit 2
}

[[ "$site_domain" =~ ^[a-z0-9][a-z0-9.-]*[a-z0-9]$ ]] || {
  echo "Invalid staging hostname" >&2
  exit 2
}
[[ "$site_domain" == *.rosta.shop ]] || {
  echo "Staging hostname must be a child of rosta.shop" >&2
  exit 2
}
test "$site_domain" != "rosta.shop"

api_domain="api.$site_domain"
media_domain="media.$site_domain"
site_url="https://$site_domain"
api_url="https://$api_domain/api/v1"
payment_redirect_hosts="$site_domain,$api_domain,sandbox.zarinpal.com"

config_id="$(
  printf '%s\n'     "site=$site_url"     "api=$api_url"     "media=https://$media_domain"     "payment_redirect_hosts=$payment_redirect_hosts"     "indexing=false"     | sha256sum | awk '{print substr($1,1,12)}'
)"

random_hex() {
  openssl rand -hex 24
}

laravel_key() {
  printf 'base64:'
  openssl rand -base64 32 | tr -d '\n'
}

mkdir -p "$OUTPUT_DIR"
chmod 700 "$OUTPUT_DIR"

app_key="$(laravel_key)"
db_password="$(random_hex)"
mysql_root_password="$(random_hex)"
redis_password="$(random_hex)"

s3_access_key="${S3_ACCESS_KEY_ID:-CHANGE_ME_BEFORE_SERVER}"
s3_secret_key="${S3_SECRET_ACCESS_KEY:-CHANGE_ME_BEFORE_SERVER}"
s3_bucket="${S3_BUCKET:-CHANGE_ME_BEFORE_SERVER}"
s3_endpoint="${S3_ENDPOINT:-CHANGE_ME_BEFORE_SERVER}"
ghcr_username="${GHCR_USERNAME:-sajadkhavas}"
ghcr_token="${GHCR_TOKEN:-}"

cat > "$OUTPUT_DIR/frontend.env" <<EOF
STAGING_SITE_DOMAIN=$site_domain
STAGING_API_DOMAIN=$api_domain
STAGING_MEDIA_DOMAIN=$media_domain
VITE_SITE_URL=$site_url
VITE_API_URL=$api_url
VITE_PAYMENT_REDIRECT_HOSTS=$payment_redirect_hosts
VITE_ALLOW_INDEXING=false
VITE_PERFORMANCE_ENDPOINT=
ACME_EMAIL=$acme_email
COMPOSE_PROJECT_NAME=rosta-staging
ROSTA_IMAGE_TAG=staging-bootstrap
ROSTA_BACKEND_ENV_FILE=/etc/rosta/staging/backend.env
ROSTA_BACKUP_RETENTION_DAYS=14
ROSTA_ACCEPTANCE_TIMEOUT_SECONDS=180
EOF

cat > "$OUTPUT_DIR/backend.env" <<EOF
APP_NAME=Rosta
APP_ENV=staging
APP_KEY=$app_key
APP_DEBUG=false
APP_URL=https://$api_domain
APP_TIMEZONE=Asia/Tehran
APP_LOCALE=fa
APP_FALLBACK_LOCALE=en
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=info
APP_FAKER_LOCALE=fa_IR
ROSTA_IMAGE_TAG=staging
ROSTA_API_PORT=8080

FRONTEND_ALLOWED_ORIGINS=$site_url

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=rosta_staging
DB_USERNAME=rosta_staging
DB_PASSWORD=$db_password
MYSQL_ROOT_PASSWORD=$mysql_root_password

SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=.$site_domain
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_COOKIE=rosta_staging_session
SANCTUM_STATEFUL_DOMAINS=$site_domain

CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=$redis_password
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_PREFIX=rosta_staging_

MAIL_MAILER=log
MAIL_FROM_ADDRESS=no-reply@$site_domain
MAIL_FROM_NAME=Rosta Staging

ROSTA_API_VERSION=v1
ROSTA_CONTRACT_VERSION=2026-07-26-r5c
ROSTA_PAYMENT_ENABLED=false
ROSTA_REFUND_ENABLED=false
ROSTA_REFUND_DUAL_CONTROL=true
ROSTA_DISPUTE_WINDOW_HOURS=72
ROSTA_CARRIER_WEBHOOK_SECRET=
ROSTA_MAX_DISPATCH_ROAST_AGE_DAYS=
ROSTA_SMS_ENABLED=false
ROSTA_OTP_ENABLED=false
ROSTA_OTP_TTL_SECONDS=120
ROSTA_OTP_RESEND_AFTER_SECONDS=60
ROSTA_OTP_MAX_ATTEMPTS=5
ROSTA_OTP_DELIVERY_MAX_ATTEMPTS=3
ROSTA_AUTH_SESSION_TTL_MINUTES=120
ROSTA_MAX_ACTIVE_SESSIONS=5
ROSTA_MAX_ADDRESSES_PER_USER=20
ROSTA_MAX_PRODUCTS_PER_ROASTERY=1000
ROSTA_MAX_MEDIA_PER_ROASTERY=10000
ROSTA_QUOTE_TTL_MINUTES=15
ROSTA_RESERVATION_TTL_MINUTES=20
ROSTA_ORDER_IDEMPOTENCY_TTL_HOURS=24

PAYMENT_DRIVER=disabled
REFUND_DRIVER=disabled
SMS_DRIVER=disabled
ORDER_SMS_PROVIDER=disabled

ROSTA_MEDIA_UPLOADS_ENABLED=true
ROSTA_MEDIA_UPLOAD_DISK=s3
ROSTA_MEDIA_PUBLIC_BASE_URL=https://$media_domain
ROSTA_MEDIA_MAX_SIZE_BYTES=12000000
ROSTA_MEDIA_MAX_PIXELS=40000000
ROSTA_MEDIA_MAX_WIDTH=8000
ROSTA_MEDIA_MAX_HEIGHT=8000
ROSTA_MEDIA_MEMORY_LIMIT_MB=128
ROSTA_MEDIA_PROCESSING_TIMEOUT_MS=15000
ROSTA_MEDIA_MAX_PROCESSING_ATTEMPTS=3
ROSTA_MEDIA_ORPHAN_RETENTION_HOURS=24
ROSTA_MEDIA_VARIANT_VERSION=v1
ROSTA_MEDIA_VARIANT_WIDTHS=320,640,1280
ROSTA_MEDIA_UPLOAD_TTL_MINUTES=15

FILESYSTEM_DISK=local
S3_ACCESS_KEY_ID=$s3_access_key
S3_SECRET_ACCESS_KEY=$s3_secret_key
S3_DEFAULT_REGION=auto
S3_BUCKET=$s3_bucket
S3_ENDPOINT=$s3_endpoint
S3_PUBLIC_URL=https://$media_domain
S3_USE_PATH_STYLE_ENDPOINT=false

ROSTA_ALLOWED_PAYMENT_REDIRECT_HOSTS=$site_domain,$api_domain
ROSTA_ALLOWED_MEDIA_HOSTS=$media_domain

PAYMENT_MERCHANT_ID=
PAYMENT_CALLBACK_URL=https://$site_domain/checkout
PAYMENT_AMOUNT_MULTIPLIER=1
PAYMENT_ATTEMPT_TTL_MINUTES=20
PAYMENT_TIMEOUT_SECONDS=10
ZARINPAL_SANDBOX=true
ZARINPAL_REQUEST_URL=https://sandbox.zarinpal.com/pg/v4/payment/request.json
ZARINPAL_VERIFY_URL=https://sandbox.zarinpal.com/pg/v4/payment/verify.json
ZARINPAL_START_PAY_URL=https://sandbox.zarinpal.com/pg/StartPay
NOTIFICATION_MAX_ATTEMPTS=5
NOTIFICATION_RETRY_SECONDS=60
NOTIFICATION_TIMEOUT_SECONDS=8
KAVENEGAR_API_KEY=
KAVENEGAR_ORDER_SENDER=
KAVENEGAR_BASE_URL=https://api.kavenegar.com/v1
KAVENEGAR_CONNECT_TIMEOUT_SECONDS=3
KAVENEGAR_TIMEOUT_SECONDS=8
KAVENEGAR_RETRY_BASE_SECONDS=30
KAVENEGAR_CIRCUIT_FAILURE_THRESHOLD=5
KAVENEGAR_CIRCUIT_WINDOW_SECONDS=300
KAVENEGAR_CIRCUIT_OPEN_SECONDS=120
KAVENEGAR_OTP_TEMPLATE_LOGIN=
KAVENEGAR_OTP_TEMPLATE_REGISTER=
KAVENEGAR_OTP_TEMPLATE_VERIFY_MOBILE=
EOF

cat > "$OUTPUT_DIR/server-entry.env" <<EOF
ROSTA_RELEASE_SHA=$EXPECTED_RELEASE_SHA
ROSTA_RELEASE_TAG=rosta-pre-server-2026-09-05
ROSTA_CONFIG_ID=$config_id

ROSTA_API_REMOTE_IMAGE=ghcr.io/sajadkhavas/rosta-api:$EXPECTED_RELEASE_SHA
ROSTA_API_WEB_REMOTE_IMAGE=ghcr.io/sajadkhavas/rosta-api-web:$EXPECTED_RELEASE_SHA
ROSTA_FRONTEND_REMOTE_IMAGE=ghcr.io/sajadkhavas/rosta-frontend:$EXPECTED_RELEASE_SHA-$config_id

ROSTA_ROOT_DIR=/srv/rosta
ROSTA_FRONTEND_ENV_PATH=/etc/rosta/staging/frontend.env
ROSTA_BACKEND_ENV_PATH=/etc/rosta/staging/backend.env
ROSTA_STATE_DIR=/var/lib/rosta/staging

STAGING_SITE_DOMAIN=$site_domain
STAGING_API_DOMAIN=$api_domain
STAGING_MEDIA_DOMAIN=$media_domain

GHCR_USERNAME=$ghcr_username
GHCR_TOKEN=$ghcr_token
EOF

chmod 600   "$OUTPUT_DIR/frontend.env"   "$OUTPUT_DIR/backend.env"   "$OUTPUT_DIR/server-entry.env"

cat > "$OUTPUT_DIR/release-request.txt" <<EOF
release_sha=$EXPECTED_RELEASE_SHA
staging_site_domain=$site_domain
staging_api_domain=$api_domain
staging_media_domain=$media_domain
config_id=$config_id
frontend_image=ghcr.io/sajadkhavas/rosta-frontend:$EXPECTED_RELEASE_SHA-$config_id
EOF
chmod 600 "$OUTPUT_DIR/release-request.txt"

sha256sum   "$OUTPUT_DIR/frontend.env"   "$OUTPUT_DIR/backend.env"   "$OUTPUT_DIR/server-entry.env"   "$OUTPUT_DIR/release-request.txt"   > "$OUTPUT_DIR/bundle.sha256"
chmod 600 "$OUTPUT_DIR/bundle.sha256"

echo "ROSTA server-ready bundle generated at: $OUTPUT_DIR"
echo "config_id=$config_id"
if grep -R -q 'CHANGE_ME_BEFORE_SERVER' "$OUTPUT_DIR"; then
  echo "status=INCOMPLETE_R2_INPUTS"
  echo "Provide S3_ACCESS_KEY_ID, S3_SECRET_ACCESS_KEY, S3_BUCKET and S3_ENDPOINT, then regenerate."
else
  echo "status=READY_FOR_VERIFY"
fi
