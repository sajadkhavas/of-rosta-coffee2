# ROSTA Production Truth Boundaries

## Rule

Source code may define an interface, configuration key, disabled adapter, validation rule and fail-closed behavior. It may not convert an unknown production fact into a fake value.

The following are **not** production truth merely because source files, mocks, sandbox URLs, example environment variables or UI surfaces exist.

## Boundary matrix

| Domain | Baseline source evidence | What must not be fabricated | Required input/evidence | Fail-closed requirement |
|---|---|---|---|---|
| SMS / OTP delivery | `backend/.env.staging.example` has `ROSTA_SMS_ENABLED=false`, `SMS_DRIVER=disabled`, `ORDER_SMS_PROVIDER=disabled` and empty provider credentials | provider selection, API key, sender, template approval, delivery SLA | approved provider/account, scoped credential, sender/template approval, real send/receive acceptance | login/notification flow must not claim SMS was delivered when provider execution is disabled or failed |
| R2 / object storage | `backend/.env.staging.example` exposes S3-compatible R2 settings with placeholder values; `scripts/audit-phase22-staging.mjs` requires real round trips | bucket, account ID, keys, endpoint, custom domain, CORS success | dedicated environment bucket, scoped keys, endpoint, public/custom domain policy, CORS policy, real PUT/GET/public-delivery/cleanup evidence | media readiness is not PASS until real storage acceptance succeeds |
| Payment | staging sets `ROSTA_PAYMENT_ENABLED=false` and `PAYMENT_DRIVER=disabled` | merchant ID, gateway enablement, callback success, provider behavior, amount multiplier, approval status | chosen provider, merchant/account approval, credential, callback/domain approval, sandbox then production verification | no order may be called paid from redirect/query state alone; provider verification remains authoritative |
| Refund | staging sets `ROSTA_REFUND_ENABLED=false` and `REFUND_DRIVER=disabled` | refund eligibility, automatic approval, provider capability, refund timing | written business policy, provider capability/credential, approval model, audited acceptance | disabled/unknown refund execution must not emit a success state |
| Payout / settlement | finance routes/OpenAPI exist, but source cannot prove a banking rail or payout account | bank, payout rail, settlement schedule, destination account, executed payout | approved banking/provider setup, legal/business settlement policy, reconciliation evidence | settlement accounting may be recorded without claiming funds moved; external movement needs verified evidence |
| Carrier / delivery | staging has an empty `ROSTA_CARRIER_WEBHOOK_SECRET`; fulfillment routes exist | carrier identity, tariff, SLA, webhook authenticity, tracking guarantees | selected carrier/API contract, credential or webhook secret, service levels, test delivery/webhook evidence | carrier events are not trusted without configured authentication and validated payloads |
| Tax | financial schemas expose tax amounts, but PS0 has no approved rate source | tax rate, taxable base, exemptions, jurisdiction rule | legal/accounting decision, effective date, jurisdiction and calculation rule | quote/order generation must not invent tax; unresolved tax policy blocks production financial acceptance |
| Commission | marketplace/finance code exists, but no PS0-approved rate is supplied | commission rate, tier, settlement deduction or effective date | signed business rule/config source, scope, effective date, versioning | no guessed percentage; missing rule must fail closed or keep the operation unavailable |
| Backup | `deploy/staging/backup.sh` and restore/rollback scripts exist | existence, retention, off-host durability, successful restore | actual scheduled execution, storage destination, retention, checksum, restore drill and owner | production readiness is not PASS from scripts alone |
| Monitoring | health/readiness commands and logs exist | monitoring vendor, alert destination, on-call owner, alert delivery, uptime claim | selected monitoring/alerting system, health endpoints, alert routes, ownership, test alert evidence | release acceptance cannot claim monitored/alerted state without a delivered test signal |

## Provider configuration rules

For every external provider:

1. interface/adapter selection must be explicit;
2. disabled or incomplete configuration must be detectable;
3. production secrets are supplied outside Git;
4. missing required input fails closed;
5. sandbox behavior is never described as production acceptance;
6. provider response is validated before changing authoritative business state;
7. evidence records identifiers/statuses needed for audit without exposing credentials or private payloads.

## Financial decisions

PS0 deliberately supplies **no** tax rate, commission rate, carrier price, bank account, settlement schedule, refund policy or payout policy.

PS4 may implement an approved rule only after receiving, at minimum:

- rule owner/approver;
- exact scope;
- unit/currency where relevant;
- effective date/version;
- rounding rule where relevant;
- rollback/correction policy;
- test examples with expected outcomes.

Unknown values must remain configuration-required or disabled.

## Secrets

Never commit:

- API keys, tokens or passwords;
- Laravel application keys;
- database/Redis credentials;
- SMS/payment/R2 credentials;
- webhook secrets;
- banking credentials;
- production environment files;
- backup payloads containing customer/business data.

Example files may contain explicit placeholders such as `CHANGE_ME` or empty values, but a placeholder is evidence only of an interface—not provider readiness.

## Evidence language

Acceptable wording:

- "adapter exists"
- "configuration key exists"
- "feature is disabled"
- "sandbox contract passes"
- "external acceptance pending"
- "real R2 round trip passed on <environment> with evidence <id>"

Unacceptable wording without evidence:

- "SMS works"
- "payments are production-ready"
- "refunds are automatic"
- "R2 is configured"
- "backups are safe"
- "monitoring is active"
- "commission is X%"
- "tax is X%"
