#!/bin/bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=/dev/null
source "$ROOT/scripts/00-env.sh"

mkdir -p "$XMT_ROOT/agent"
cp -a "$ROOT/agent/." "$XMT_ROOT/agent/"
chmod +x "$XMT_ROOT/agent/run_agent.py"

# Cron every 30 minutes
CRON_LINE="*/30 * * * * cd $XMT_ROOT && XMT_ROOT=$XMT_ROOT /usr/bin/python3 $XMT_ROOT/agent/run_agent.py >> /home/wwwlogs/xmt-agent.log 2>&1"
(crontab -l 2>/dev/null | grep -v 'xmt-agent' || true; echo "$CRON_LINE") | crontab -

# Drupal cron hourly via drush for all sites
DRUPAL_CRON="15 * * * * cd $XMT_ROOT && for u in xmt.pub zhubao.pub airobotor.com hm-os.com kstudy.com.cn drupal.org.cn itra.com.cn; do $DRUSH --uri=\$u cron >/dev/null 2>&1; done"
(crontab -l 2>/dev/null | grep -v 'xmt drush cron' || true; echo "$DRUPAL_CRON") | crontab -

echo "==> Agent installed; running first collection..."
/usr/bin/python3 "$XMT_ROOT/agent/run_agent.py" || true
echo "==> Agent cron configured"
crontab -l | grep xmt || true
