# PS2 — OTP and notification delivery runbook

## Production activation

OTP is a required production dependency. Production readiness stays failed until all of the following are true:

- `ROSTA_OTP_ENABLED=true`
- `SMS_DRIVER=kavenegar`
- `KAVENEGAR_API_KEY` is a scoped production credential
- `KAVENEGAR_BASE_URL=https://api.kavenegar.com/v1`
- `KAVENEGAR_OTP_TEMPLATE_LOGIN`, `KAVENEGAR_OTP_TEMPLATE_REGISTER`, and `KAVENEGAR_OTP_TEMPLATE_VERIFY_MOBILE` name templates already created and approved in Kavenegar
- database, Redis, the notification worker, and the `notifications` queue are healthy

Order SMS is activated independently with `ROSTA_SMS_ENABLED=true`, `ORDER_SMS_PROVIDER=kavenegar`, and an approved `KAVENEGAR_ORDER_SENDER`. Never put real credentials in an example file, CI variable output, ticket, or log.

## Retry and duplicate policy

Kavenegar send endpoints do not expose an idempotency key. Each HTTP operation therefore makes exactly one network attempt. Only an explicit temporary provider rejection (provider status `409`, `429`, or `451`, or HTTP `429`) may be retried by the durable OTP/outbox state machine with bounded backoff and jitter.

Timeouts, connection errors, 5xx responses, malformed success responses, interrupted workers, and database failures after provider acceptance are treated as **unknown outcomes**. They are dead-lettered and never sent automatically again. This trades a possible missed message for protection against duplicate OTP/order messages. Operators must inspect the provider panel and the stored provider message identifier before any manual action.

OTP delivery states are `pending`, `processing`, `sent`, `failed`, and `unknown`. An `unknown` challenge can still be verified if the customer received it, but a worker cannot resend it. A new user request supersedes the old challenge after the normal resend window.

## Redaction and local development

Logs may contain a challenge identifier, a provider message identifier, a purpose, and the final four digits of a mobile number. They must never contain an OTP code, full mobile number, API key, rendered SMS body, raw provider response, or exception URL.

The local `log` driver stores the OTP encrypted in cache and logs only redacted metadata. Consume it once with:

```bash
php artisan rosta:local-otp <challenge-ulid>
```

The `acceptance` sender and `rosta:acceptance-otp` command are testing-only. The order `testing` provider is also testing-only. Neither driver is production-ready.

## Verification

Offline verification never sends a paid SMS:

```bash
cd backend
composer audit:ps2
php artisan test --filter='KavenegarOtpProviderTest|OtpDeliverySafetyTest|IdentityOtpTest|NotificationOutboxTest|HealthTest'
php artisan rosta:readiness --json
composer check
```

Before production activation, run one separately approved paid acceptance using a dedicated test mobile. Confirm receipt, one provider message only, template rendering, provider message ID persistence, readiness recovery after a simulated circuit opening, and absence of secrets/full mobile/OTP/body in application and worker logs. Record only redacted evidence.

## Incident handling

1. Disable new sends with `ROSTA_OTP_ENABLED=false` or `ROSTA_SMS_ENABLED=false` as appropriate; production readiness will fail visibly for OTP.
2. Inspect counts of `unknown` OTP deliveries and failed notification outbox rows. Do not replay them in bulk.
3. Reconcile each unknown outcome against the Kavenegar provider panel using timestamps, suffixes, and provider message IDs.
4. Rotate the API key immediately if it may have appeared in output or a URL log.
5. Re-enable only after provider health and templates are confirmed, the circuit cool-down has elapsed, and a redacted acceptance succeeds.
