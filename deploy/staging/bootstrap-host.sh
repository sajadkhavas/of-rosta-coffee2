#!/usr/bin/env bash
set -Eeuo pipefail

[[ "${EUID}" -eq 0 ]] || { echo "Run as root: sudo $0" >&2; exit 1; }

# shellcheck disable=SC1091
source /etc/os-release
[[ "${ID:-}" == "ubuntu" ]] || { echo "Ubuntu is required" >&2; exit 1; }

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y --no-install-recommends \
  ca-certificates \
  curl \
  git \
  gnupg \
  gzip \
  jq \
  openssl \
  python3 \
  rsync \
  ufw

install -m 0755 -d /etc/apt/keyrings
if [[ ! -f /etc/apt/keyrings/docker.asc ]]; then
  curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
    -o /etc/apt/keyrings/docker.asc
  chmod a+r /etc/apt/keyrings/docker.asc
fi

architecture="$(dpkg --print-architecture)"
. /etc/os-release
cat > /etc/apt/sources.list.d/docker.sources <<EOF
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: ${VERSION_CODENAME}
Components: stable
Architectures: ${architecture}
Signed-By: /etc/apt/keyrings/docker.asc
EOF

apt-get update
apt-get install -y --no-install-recommends \
  docker-ce \
  docker-ce-cli \
  containerd.io \
  docker-buildx-plugin \
  docker-compose-plugin

systemctl enable --now docker

deploy_user="${ROSTA_DEPLOY_USER:-rosta-deploy}"
if ! id "$deploy_user" >/dev/null 2>&1; then
  useradd --create-home --shell /bin/bash "$deploy_user"
fi
usermod -aG docker "$deploy_user"

install -d -m 0750 -o "$deploy_user" -g "$deploy_user" /srv/rosta
install -d -m 0750 -o "$deploy_user" -g "$deploy_user" /etc/rosta/staging
install -d -m 0700 -o "$deploy_user" -g "$deploy_user" /var/lib/rosta/staging
install -d -m 0700 -o "$deploy_user" -g "$deploy_user" /var/lib/rosta/staging/backups
install -d -m 0700 -o "$deploy_user" -g "$deploy_user" /var/lib/rosta/staging/reports

if [[ "${ROSTA_CONFIGURE_UFW:-true}" == "true" ]]; then
  ufw default deny incoming
  ufw default allow outgoing
  ufw allow OpenSSH
  ufw allow 80/tcp
  ufw allow 443/tcp
  ufw allow 443/udp
  ufw --force enable
fi

cat <<EOF
Rosta staging host bootstrap completed.

Next:
1. Clone the private repository under /srv/rosta as ${deploy_user}.
2. Store frontend env at /etc/rosta/staging/frontend.env.
3. Store backend env at /etc/rosta/staging/backend.env.
4. Point staging, api-staging and media-staging DNS records before deploy.
5. Run deploy/staging/preflight.sh, then deploy/staging/deploy.sh as ${deploy_user}.

Log out and back in before ${deploy_user} uses Docker group access.
EOF
