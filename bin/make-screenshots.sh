#!/usr/bin/env bash
# Renders the real interface (css/js of this repository) with demo data in headless Chrome. Output: docs/screenshots/*.png
set -euo pipefail
cd "$(dirname "$0")/.."
CHROME="${CHROME:-/Applications/Google Chrome.app/Contents/MacOS/Google Chrome}"
H="file://$PWD/tests/screenshots/harness.html"
shot() { # name state width height budget-ms [extra chrome flags]
  "$CHROME" --headless=new ${6:-} --disable-gpu --hide-scrollbars --force-device-scale-factor=1 --window-size="$3,$4" --virtual-time-budget="$5" \
    --screenshot="docs/screenshots/$1.png" "$H?state=$2" >/dev/null 2>&1
  echo "docs/screenshots/$1.png"
}
STILL=--force-prefers-reduced-motion   # still frames: counters and entrance animations are already in their final state
shot 1-protected ok 1280 1500 4000 "$STILL"
shot 2-attack attack 1280 1750 6000 "$STILL"
shot 3-rewinding rewinding 1280 760 3000
shot 4-done done 1280 760 4700 "$STILL"
shot 5-phone ok 400 1700 4000 "$STILL"
