#!/bin/bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=/dev/null
source "$ROOT/scripts/00-env.sh"

cd "$XMT_ROOT"

# Contrib modules needed for themes + syndication
composer require --no-interaction \
  drupal/token \
  drupal/pathauto \
  drupal/admin_toolbar \
  drupal/ctools \
  drupal/views_infinite_scroll \
  drupal/metatag \
  2>&1 | tail -30

enable_for() {
  local uri="$1"
  shift
  $DRUSH --uri="$uri" en "$@" -y 2>&1 | tail -15
}

# Themes + base modules per site
for uri in xmt.pub zhubao.pub airobotor.com hm-os.com; do
  echo "==> Sancy stack on $uri"
  $DRUSH --uri="$uri" theme:enable gavias_sancy -y 2>&1 | tail -10 || true
  $DRUSH --uri="$uri" config:set system.theme default gavias_sancy -y 2>&1 | tail -5 || true
  enable_for "$uri" pathauto token admin_toolbar || true
done

for uri in kstudy.com.cn drupal.org.cn itra.com.cn; do
  echo "==> Kiamo stack on $uri"
  $DRUSH --uri="$uri" theme:enable gavias_kiamo -y 2>&1 | tail -10 || true
  $DRUSH --uri="$uri" config:set system.theme default gavias_kiamo -y 2>&1 | tail -5 || true
  enable_for "$uri" pathauto token admin_toolbar || true
done

# Deploy custom XMT modules (syndicate + trust stack mirrors if present)
mkdir -p "$XMT_WEB/modules/custom"
if [ -d "$ROOT/modules" ]; then
  for mod in "$ROOT"/modules/xmt_*; do
    [ -d "$mod" ] || continue
    cp -a "$mod" "$XMT_WEB/modules/custom/"
  done
fi

for uri in xmt.pub zhubao.pub airobotor.com hm-os.com kstudy.com.cn drupal.org.cn itra.com.cn; do
  $DRUSH --uri="$uri" en xmt_syndicate node taxonomy field field_ui text path path_alias views -y 2>&1 | tail -10 || true
done

echo "==> Themes and modules enabled"
