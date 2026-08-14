#!/bin/bash
# Enable trust modules and fields on vertical sites (non-hub).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=/dev/null
source "$ROOT/scripts/00-env.sh"

VERTICAL_URIS=(
  zhubao.pub
  airobotor.com
  hm-os.com
  kstudy.com.cn
  drupal.org.cn
  itra.com.cn
)

for uri in "${VERTICAL_URIS[@]}"; do
  echo "==> Trust bootstrap on $uri"
  $DRUSH --uri="$uri" en xmt_trust xmt_publisher xmt_trust_ui -y 2>&1 | tail -5 || true
  $DRUSH --uri="$uri" php:eval 'xmt_trust_ensure_fields(); xmt_trust_ensure_roles(); echo "trust fields ok\n";' 2>&1 | tail -3
  $DRUSH --uri="$uri" cr 2>&1 | tail -2
done

echo "==> Vertical trust bootstrap complete"
