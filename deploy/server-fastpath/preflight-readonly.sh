#!/usr/bin/env bash
set -u

CONFIG_FILE="${1:-/etc/rosta/staging/server-entry.env}"

echo "============================================================"
echo " ROSTA SERVER FAST PATH — READ-ONLY PREFLIGHT"
echo "============================================================"

if [[ -f "$CONFIG_FILE" ]]; then
  # shellcheck disable=SC1090
  set -a
  source "$CONFIG_FILE"
  set +a
  echo "server_entry_config=present"
else
  echo "server_entry_config=missing:$CONFIG_FILE"
fi

echo
echo "=== OS / KERNEL / ARCH ==="
cat /etc/os-release 2>/dev/null || true
uname -a 2>/dev/null || true
printf 'arch='; uname -m 2>/dev/null || true
printf 'cpu='; nproc 2>/dev/null || true

echo
echo "=== MEMORY / SWAP ==="
free -h 2>/dev/null || true
swapon --show 2>/dev/null || true

echo
echo "=== DISK ==="
df -hT / /srv /var 2>/dev/null || true

echo
echo "=== CLOCK ==="
date -Is 2>/dev/null || true
timedatectl status 2>/dev/null || true

echo
echo "=== DOCKER ==="
docker --version 2>&1 || true
docker compose version 2>&1 || true
docker info --format 'Server={{.ServerVersion}} CPUs={{.NCPU}} Memory={{.MemTotal}} Root={{.DockerRootDir}}' 2>&1 || true

echo
echo "=== LISTENERS 22/80/443 ==="
ss -lntup 2>/dev/null | grep -E ':(22|80|443)\b' || true

echo
echo "=== FIREWALL ==="
ufw status verbose 2>&1 || true
iptables -S DOCKER-USER 2>&1 || true

echo
echo "=== ROSTA PATHS ==="
for path in   /srv/rosta   /etc/rosta/staging   /etc/rosta/staging/frontend.env   /etc/rosta/staging/backend.env   /etc/rosta/staging/server-entry.env   /var/lib/rosta/staging
do
  if [[ -e "$path" ]]; then
    stat -c '%A %a %U:%G %n' "$path" 2>/dev/null || true
  else
    echo "MISSING $path"
  fi
done

echo
echo "=== RELEASE REPOSITORY ==="
if [[ -d "${ROSTA_ROOT_DIR:-/srv/rosta}/.git" ]]; then
  git -C "${ROSTA_ROOT_DIR:-/srv/rosta}" status --short --branch 2>/dev/null || true
  printf 'head='
  git -C "${ROSTA_ROOT_DIR:-/srv/rosta}" rev-parse HEAD 2>/dev/null || true
else
  echo "repository=missing"
fi

echo
echo "=== DNS ==="
for name in   "${STAGING_SITE_DOMAIN:-}"   "${STAGING_API_DOMAIN:-}"   "${STAGING_MEDIA_DOMAIN:-}"
do
  [[ -n "$name" ]] || continue
  printf '%s => ' "$name"
  getent ahostsv4 "$name" 2>/dev/null | awk 'NR==1{print $1}' || true
done

echo
echo "=== RECENT OOM ==="
journalctl -k --no-pager -n 300 2>/dev/null   | grep -Ei 'out of memory|oom-killer|killed process'   || echo "no_recent_oom_found_or_log_unavailable"

echo
echo "============================================================"
echo " READ-ONLY PREFLIGHT COMPLETE — NO MUTATION PERFORMED"
echo "============================================================"
