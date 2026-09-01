# PS9 Official References

This register records the external documentation used to design the final pre-server freeze. It is evidence of the reviewed contract, not proof that any external provider or real server is active.

## GitHub release identity and workflow security

- About releases — releases are based on Git tags and mark a point in repository history: https://docs.github.com/en/repositories/releasing-projects-on-github/about-releases
- Secure use reference — pinning a third-party action to a full-length commit SHA is the immutable action reference: https://docs.github.com/en/actions/reference/security/secure-use
- Automatic token authentication — permissions and workflow behavior of `GITHUB_TOKEN`: https://docs.github.com/en/actions/security-for-github-actions/security-guides/automatic-token-authentication
- Export dependencies as an SBOM — GitHub documents SPDX SBOM export/generation and supply-chain use: https://docs.github.com/en/code-security/how-tos/secure-your-supply-chain/establish-provenance-and-integrity/export-dependencies-as-sbom

## Runtime/package acceptance

- Docker Compose service dependencies and health-gated startup: https://docs.docker.com/reference/compose-file/services/
- Docker Compose startup ordering and `service_healthy`: https://docs.docker.com/compose/how-tos/startup-order/
- Docker build best practices and image pinning considerations: https://docs.docker.com/build/building/best-practices/

## API/provider blocker references retained from the pre-PS9 audit

- Laravel middleware groups and middleware configuration: https://laravel.com/docs/13.x/middleware
- Laravel routing: https://laravel.com/docs/13.x/routing
- Zarinpal payment gateway connection flow: https://www.zarinpal.com/docs/paymentGateway/connectToGateway

## Truth boundary

Official documentation can establish the intended source contract. It cannot prove real DNS/TLS, credentials, provider approval, merchant/account activation, live database state, off-host backup retention or delivered monitoring alerts. Those remain post-freeze server/runtime acceptance facts.
