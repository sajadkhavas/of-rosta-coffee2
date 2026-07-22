# وضعیت انتقال معماری Cooci به Rosta

آخرین به‌روزرسانی: 2026-07-22

## تکمیل‌شده در Package A

- Payment Attempt provider-neutral
- Disabled/Testing/Zarinpal adapters
- Idempotent request, callback and verification
- Atomic settlement of `stock_on_hand` and `stock_reserved`
- Paid-but-unallocated financial review boundary
- Notification Outbox with encrypted payloads
- Disabled/Testing/Kavenegar order notification providers
- Versioned templates, retry, stale recovery and scheduler
- Payment and Outbox feature tests
- Permanent adoption audit in `composer check`

Branch: `agent/phase-18-cooci-adoption`
PR: `#17` into `integration/rosta-complete-build`

## تکمیل‌شده در Package B

- Controlled SubOrder state machine
- Seller roastery-scoped transition endpoint
- Administrator transition endpoint using the same domain service
- Acceptance, preparation, ready-to-ship, shipment and delivery
- Mandatory carrier and tracking code before shipment
- Shipment persistence and customer-safe tracking response
- Append-only SubOrder status history
- Encrypted append-only internal notes
- Paid seller rejection → `refund_pending`, not fake cancellation
- Exactly-once restock guard plus append-only Stock Ledger evidence
- Notification Outbox integration for operational transitions
- IDOR, invalid transition, tracking and repeated-restock tests
- Permanent fulfillment audit in `composer check`

Branch: `agent/phase-18b-fulfillment`
Target: stacked on Package A

## بسته بعدی

Package C:

- Verified-purchase reviews
- Public review summary from approved records only
- Admin moderation
- Inquiry/support persistence
- Honeypot, HMAC IP, duplicate window and rate limiting
- Replace contact `mailto` with real persisted API

## Gateهای باز سراسری

- `backend/composer.lock` هنوز باید در محیط دارای Composer/Registry تولید و Commit شود.
- GitHub Actions مخزن پیش از Checkout متوقف می‌شود؛ هیچ نتیجه سبز/قرمز کدی از Runner فعلی قابل استناد نیست.
- تست‌ها و Larastan/Pint باید روی Docker یا Runner سالم اجرا شوند.
- Provider credentials همچنان وارد نشده‌اند.
- Staging و Production activation عمداً تا تکمیل محصول غیرفعال‌اند.
- هیچ یک از PRهای این زنجیره نباید فعلاً به `main` Merge شوند.
