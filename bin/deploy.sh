#!/usr/bin/env bash
# Packs and installs the app into a Nextcloud AIO container over ssh. Usage: bin/deploy.sh user@host [container]
set -euo pipefail
cd "$(dirname "$0")/.."
HOST="${1:?ssh target}"; C="${2:-nextcloud-aio-nextcloud}"
TGZ="$(bin/pack.sh | tail -n1)"
scp -q "$TGZ" "$HOST:/root/hc-app.tgz"
ssh "$HOST" "set -e; docker cp /root/hc-app.tgz $C:/tmp/hc-app.tgz; rm -f /root/hc-app.tgz
docker exec $C sh -c 'cd /var/www/html/custom_apps && rm -rf rewind && tar xzf /tmp/hc-app.tgz && chown -R www-data:www-data rewind && rm /tmp/hc-app.tgz'
# a new version needs the update step at once, otherwise Nextcloud shows its update page
if docker exec -u www-data $C php occ status --output=json | grep -q '\"needsDbUpgrade\":true'; then docker exec -u www-data $C php occ upgrade >/dev/null 2>&1 || true; fi
docker exec -u www-data $C php occ app:enable rewind >/dev/null && docker exec -u www-data $C php occ app:list | grep -E '^  - rewind'"
