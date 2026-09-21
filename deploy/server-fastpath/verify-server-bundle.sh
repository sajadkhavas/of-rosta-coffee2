#!/usr/bin/env bash
set -Eeuo pipefail

DIR="${1:-.server-ready}"
EXPECTED_RELEASE_SHA="4a54780d504b91527a86777e7f04368022354686"

fail() {
  printf '[verify-server-ready] ERROR: %s\n' "$*" >&2
  exit 1
}

for command in grep sha256sum stat; do
  command -v "$command" >/dev/null 2>&1 || fail "Missing command: $command"
done

for file in frontend.env backend.env server-entry.env release-request.txt bundle.sha256; do
  [[ -s "$DIR/$file" ]] || fail "Missing or empty $DIR/$file"
done

sha256sum -c "$DIR/bundle.sha256"

if grep -R -E 'CHANGE_ME|CHANGE_ME_BEFORE_SERVER'   "$DIR/frontend.env" "$DIR/backend.env" "$DIR/server-entry.env" >/dev/null; then
  fail "Bundle still contains unresolved placeholders"
fi

# shellcheck disable=SC1090
set -a
source "$DIR/frontend.env"
source "$DIR/backend.env"
source "$DIR/server-entry.env"
set +a

test "$ROSTA_RELEASE_SHA" = "$EXPECTED_RELEASE_SHA"   || fail "Wrong release SHA"

test "$APP_ENV" = "staging"
test "$APP_DEBUG" = "false"
test "$VITE_ALLOW_INDEXING" = "false"
test "$ROSTA_PAYMENT_ENABLED" = "false"
test "$ROSTA_REFUND_ENABLED" = "false"
test "$ROSTA_SMS_ENABLED" = "false"
test "$ROSTA_MEDIA_UPLOADS_ENABLED" = "true"
test "$ROSTA_MEDIA_UPLOAD_DISK" = "s3"

test "$STAGING_API_DOMAIN" = "api.$STAGING_SITE_DOMAIN"
test "$STAGING_MEDIA_DOMAIN" = "media.$STAGING_SITE_DOMAIN"
test "$SESSION_DOMAIN" = ".$STAGING_SITE_DOMAIN"
test "$SANCTUM_STATEFUL_DOMAINS" = "$STAGING_SITE_DOMAIN"
test "$FRONTEND_ALLOWED_ORIGINS" = "https://$STAGING_SITE_DOMAIN"
test "$VITE_SITE_URL" = "https://$STAGING_SITE_DOMAIN"
test "$VITE_API_URL" = "https://$STAGING_API_DOMAIN/api/v1"
test "$APP_URL" = "https://$STAGING_API_DOMAIN"
test "$S3_PUBLIC_URL" = "https://$STAGING_MEDIA_DOMAIN"
test "$ROSTA_MEDIA_PUBLIC_BASE_URL" = "https://$STAGING_MEDIA_DOMAIN"

for file in "$DIR/frontend.env" "$DIR/backend.env" "$DIR/server-entry.env"; do
  mode="$(stat -c '%a' "$file")"
  other="${mode: -1}"
  test "$other" = "0" || fail "$file is accessible to other users (mode $mode)"
done

echo "server_ready_bundle=valid"
echo "release=$ROSTA_RELEASE_SHA"
echo "site=$STAGING_SITE_DOMAIN"
echo "api=$STAGING_API_DOMAIN"
echo "media=$STAGING_MEDIA_DOMAIN"
echo "config_id=$ROSTA_CONFIG_ID"
