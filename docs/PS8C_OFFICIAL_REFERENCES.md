# PS8C — Official Infrastructure References

Reviewed: 2026-08-31.

This note records the external contracts used for PS8C. It is evidence of design guidance, not evidence that the Rosta production VPS is already configured.

## Docker Compose

- Services / `depends_on` / healthchecks: https://docs.docker.com/reference/compose-file/services/
- Startup order and `service_healthy`: https://docs.docker.com/compose/how-tos/startup-order/
- Networks and `internal: true`: https://docs.docker.com/reference/compose-file/networks/
- Compose networking: https://docs.docker.com/compose/how-tos/networking/

PS8C applies these contracts by health-gating dependency startup, isolating the backend network and preventing MySQL/Redis from publishing host ports.

## Docker image/build immutability

- Build best practices: https://docs.docker.com/build/building/best-practices/
- Dockerfile `FROM` reference: https://docs.docker.com/reference/dockerfile
- Pull by digest: https://docs.docker.com/reference/cli/docker/image/pull/

Docker documents that tags are mutable and that digest references provide immutable image identity. Rosta currently forbids `latest` and keys its own application images by release SHA, but PS8C deliberately records that external base/runtime digest pinning is not yet claimed.

## Caddy

- Automatic HTTPS: https://caddyserver.com/docs/automatic-https
- HTTPS quick start: https://caddyserver.com/docs/quick-starts/https

Caddy documents that public automatic HTTPS requires valid public DNS, externally reachable ports 80/443, writable persistent data storage and the domain in configuration. PS8C rehearses the edge contract locally but leaves those real-host facts for server/runtime acceptance.
