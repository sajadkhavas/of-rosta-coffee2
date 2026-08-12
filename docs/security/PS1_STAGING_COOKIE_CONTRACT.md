# PS1 Staging Cookie Isolation Contract

PS1 resolves the PS0 staging-cookie blocker without changing the production domain contract.

## Host topology

- frontend: `https://staging.rosta.shop`
- API: `https://api.staging.rosta.shop/api/v1`
- media: `https://media.staging.rosta.shop`

Stateful browser cookies are scoped only to `.staging.rosta.shop`. Production `rosta.shop` and `api.rosta.shop` are not children of that cookie domain and therefore cannot receive staging cookies.

## Cookie names and CSRF

- Laravel session cookie: `rosta_staging_session`
- session cookie domain: `.staging.rosta.shop`
- session cookie: Secure, HttpOnly, SameSite=Lax
- XSRF cookie name: Laravel/Sanctum `XSRF-TOKEN`
- XSRF cookie domain: `.staging.rosta.shop`
- frontend continues to send `X-XSRF-TOKEN` and credentialed requests through the existing `src/lib/api/client.ts` contract.

The public suffix `.rosta.shop` is forbidden for staging session/XSRF scope.

## Acceptance

`deploy/staging/lib.sh` fails closed if the staging host hierarchy, session name/domain or stateful-origin contract drifts. `deploy/staging/acceptance.sh` validates the runtime Set-Cookie attributes. `deploy/staging/contract-test.sh` validates the source-level lock and cookie contract without requiring secrets or Docker.

Production cookie names/domains are not defined by this document and are not changed by PS1.
