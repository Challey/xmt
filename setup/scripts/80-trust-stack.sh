#!/bin/bash
set -euo pipefail
# Enable XMT trust stack, ensure fields/roles, place homepage block on hub.
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=/dev/null
source "$ROOT/scripts/00-env.sh"

cd "$XMT_ROOT"

HUB_URI="xmt.pub"
VERTICAL_URIS=(zhubao.pub airobotor.com hm-os.com kstudy.com.cn drupal.org.cn itra.com.cn)

echo "==> Enabling trust modules on hub ($HUB_URI)"
$DRUSH --uri="$HUB_URI" en xmt_publisher xmt_trust xmt_trust_ui xmt_dx_bridge -y 2>&1 | tail -20 || true
$DRUSH --uri="$HUB_URI" php:eval 'xmt_trust_ensure_fields(); xmt_trust_ensure_roles(); echo "hub fields/roles ok\n";' 2>&1 | tail -10 || true

for uri in "${VERTICAL_URIS[@]}"; do
  echo "==> Enabling trust modules on vertical $uri"
  $DRUSH --uri="$uri" en xmt_publisher xmt_trust xmt_trust_ui -y 2>&1 | tail -15 || true
  $DRUSH --uri="$uri" php:eval 'xmt_trust_ensure_fields(); xmt_trust_ensure_roles(); echo "ok\n";' 2>&1 | tail -5 || true
done

echo "==> Placing homepage trust columns block on $HUB_URI"
$DRUSH --uri="$HUB_URI" php:eval "
\$s = \Drupal::entityTypeManager()->getStorage('block');
foreach (\$s->loadByProperties(['plugin' => 'xmt_trust_home_columns']) as \$b) { \$b->delete(); }
\$s->create([
  'id' => 'gavias_sancy_xmt_trust_home',
  'plugin' => 'xmt_trust_home_columns',
  'region' => 'content',
  'theme' => 'gavias_sancy',
  'weight' => -50,
  'status' => TRUE,
  'settings' => ['label' => '信媒体 · 可信分区', 'label_display' => 'visible'],
])->save();
echo \"block placed\\n\";
" 2>&1 | tail -10 || true

$DRUSH --uri="$HUB_URI" cr 2>&1 | tail -5 || true
echo "==> Trust stack bootstrap done"
