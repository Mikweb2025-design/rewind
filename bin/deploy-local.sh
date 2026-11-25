#!/usr/bin/env bash
# Copies the app into the local test container and enables it. Usage: bin/deploy-local.sh [container]
set -euo pipefail
cd "$(dirname "$0")/.."
C="${1:-nc-rewind}"
COPYFILE_DISABLE=1 tar --no-xattrs --no-mac-metadata --exclude='.git' --exclude='dist' --exclude='tests' -czf /tmp/rewind-dev.tgz -C .. rewind
docker cp /tmp/rewind-dev.tgz "$C:/tmp/rewind-dev.tgz"
docker exec "$C" sh -c 'cd /var/www/html/custom_apps && rm -rf rewind && tar xzf /tmp/rewind-dev.tgz && chown -R www-data:www-data rewind && rm /tmp/rewind-dev.tgz'
docker exec -u www-data "$C" php occ app:enable rewind 2>&1 | tail -2
docker exec -u www-data "$C" php occ upgrade >/dev/null 2>&1 || true
docker exec -u www-data "$C" php occ app:list | grep -E '^  - rewind'
