# Runbook پشتیبان‌گیری و بازیابی Rosta

این سند بخشی از Gate انتشار است. وجود Backup بدون Restore Drill به‌معنی آمادگی نیست.

## دامنه داده

### منبع حقیقت

- MySQL: کاربران، کاتالوگ، موجودی، سفارش، Payment Attempt، Outbox، Shipment، Review، Inquiry و CMS
- Object Storage: تصاویر و فایل‌های MediaAsset
- Secret Store: APP_KEY، Database credentials، Redis password، Payment/SMS/Object Storage credentials

### منبع حقیقت نیست

- Redis Cache
- Redis Session در سناریوی Disaster Recovery
- Queueهای درحال اجرا
- فایل‌های Build محلی

این موارد پس از Restore بازسازی می‌شوند.

## سیاست پیشنهادی

- MySQL full backup روزانه با نگهداری حداقل ۳۰ روز
- Backup رمزگذاری‌شده خارج از همان VPS
- Object Storage versioning و lifecycle retention
- نسخه هفتگی مستقل از Bucket اصلی
- Export تنظیمات DNS/Cloudflare و Secret inventory بدون مقدار Secret
- Restore Drill حداقل ماهانه روی محیط ایزوله

## ایجاد Backup MySQL

```bash
set -Eeuo pipefail
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
out="rosta-mysql-${stamp}.sql.gz"

docker compose --env-file .env.staging -f docker-compose.staging.yml exec -T mysql \
  sh -c 'exec mysqldump --single-transaction --quick --routines --triggers \
    -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
  | gzip -9 > "$out"

test -s "$out"
sha256sum "$out" > "${out}.sha256"
```

Backup و checksum باید قبل از انتقال به Storage رمزگذاری‌شده بررسی شوند. Password نباید در Command History تایپ شود؛ Environment باید از Secret Store تزریق شود.

## Snapshot Object Storage

برای R2/S3 از Inventory/Replication یا ابزار همگام‌سازی با Destination مستقل استفاده شود. حداقل شواهد لازم:

- Bucket source و destination متفاوت
- تعداد Objectها و مجموع Bytes
- Snapshot timestamp
- Hash/ETag یا Inventory manifest
- Retention policy
- آزمون دسترسی به چند Object تصادفی

Objectهای `media_upload_intents` منقضی‌شده لازم نیست بازیابی شوند؛ MediaAssetهای ثبت‌شده باید Object متناظر داشته باشند.

## Restore Drill

Restore هرگز روی Production موجود آزمایش نمی‌شود.

1. محیط ایزوله با DB و Bucket تازه بسازید.
2. Commit و Release Manifest متناظر Backup را Checkout کنید.
3. Secretهای Drill را جداگانه ایجاد کنید؛ Production Secret را کپی نکنید.
4. فایل Backup را با checksum تأیید کنید.
5. دیتابیس را Restore کنید:

```bash
gzip -dc rosta-mysql-YYYYMMDDTHHMMSSZ.sql.gz \
  | docker compose exec -T mysql mysql \
      -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE"
```

6. Object Snapshot را به Bucket Drill برگردانید.
7. Cache و Queue را پاک/بازسازی کنید.
8. `php artisan rosta:readiness --json` را اجرا کنید.
9. `composer check` و Frontend `bun run release:verify` را اجرا کنید.
10. Acceptance زیر را انجام دهید.

## Restore Acceptance

- Migration table و Required tables کامل‌اند.
- تعداد Orderها، OrderItemها و PaymentAttemptها با Snapshot metadata سازگار است.
- هیچ Payment Attempt verified بدون Order paid وجود ندارد.
- هیچ Reservation consumed با stock منفی وجود ندارد.
- MediaAssetهای نمونه از CDN Drill پاسخ موفق می‌دهند.
- Login، Order history، Product detail و Content SSR کار می‌کنند.
- Worker و Scheduler پس از Restore بدون Duplicate notification اجرا می‌شوند.
- Providerهای واقعی در Drill خاموش‌اند.
- Google indexing خاموش است.

## شواهد Drill

برای هر Drill نگهداری شود:

- تاریخ و مسئول اجرا
- Backup IDs و SHA-256
- Commit SHA و Release Manifest
- زمان شروع و پایان Restore
- RPO و RTO مشاهده‌شده
- خروجی Readiness و Test gates
- خطاها و اقدام اصلاحی

## Rollback انتشار

Rollback برنامه و Rollback داده یکسان نیستند.

- کد: Image/Worker artifact قبلی با Manifest معتبر Deploy می‌شود.
- Migration additive: معمولاً Rollback کد بدون Down migration انجام می‌شود.
- Migration مخرب: تا پایان دوره سازگاری مجاز نیست.
- داده مالی: هرگز با Restore عمومی برای اصلاح یک تراکنش منفرد بازنویسی نمی‌شود؛ Reconciliation/Audit استفاده می‌شود.
