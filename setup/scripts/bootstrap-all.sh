#!/bin/bash
set -euo pipefail
# Full bootstrap for XMT Drupal 11.4 multisite + agent
SETUP="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=/dev/null
source "$SETUP/scripts/00-env.sh"

echo "==== XMT Bootstrap $(date) ===="

if [ ! -f "$XMT_ROOT/web/index.php" ]; then
  echo "Drupal not found at $XMT_ROOT — run composer create-project first"
  exit 1
fi

bash "$SETUP/scripts/10-extract-themes.sh"
bash "$SETUP/scripts/20-multisite-config.sh"
bash "$SETUP/scripts/30-install-sites.sh"
bash "$SETUP/scripts/40-themes-modules.sh"
bash "$SETUP/scripts/50-nginx-hosts.sh"
bash "$SETUP/scripts/60-content-fields.sh"
bash "$SETUP/scripts/70-agent-cron.sh"
bash "$SETUP/scripts/80-trust-stack.sh"

echo "==== Bootstrap complete ===="
echo "Admin: $SITE_ADMIN_USER / $SITE_ADMIN_PASS"
echo "Open http://xmt.wsl/ (or xmt.pub via hosts)"
for u in xmt.pub zhubao.pub airobotor.com hm-os.com kstudy.com.cn drupal.org.cn itra.com.cn; do
  $DRUSH --uri="$u" status --fields=drupal-version,site,db-status,theme 2>/dev/null || true
done
