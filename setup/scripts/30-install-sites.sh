#!/bin/bash
# Site installer for Drupal 11.4 string-prefix multisite (shared DB xmt_multi)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=/dev/null
source "$ROOT/scripts/00-env.sh"

cd "$XMT_ROOT"

is_bootstrapped() {
  local uri="$1"
  $DRUSH --uri="$uri" status --field=bootstrap 2>/dev/null | grep -qi Successful
}

install_one() {
  local uri="$1"
  local name="$2"
  local prefix="$3"

  if is_bootstrapped "$uri"; then
    echo "SKIP already installed: $uri"
    return 0
  fi

  echo "INSTALL $uri ($name) prefix=$prefix"
  # Ensure settings use string prefix
  settings="$XMT_WEB/sites/${uri}/settings.php"
  if [ -f "$settings" ]; then
    # no-op marker for logs
    grep -q "'prefix' =>" "$settings" && echo "settings prefix ok for $uri"
  fi

  local args=(
    site:install standard
    --uri="$uri"
    --site-name="$name"
    --site-mail="$SITE_ADMIN_MAIL"
    --account-name="$SITE_ADMIN_USER"
    --account-pass="$SITE_ADMIN_PASS"
    --account-mail="$SITE_ADMIN_MAIL"
    --yes
  )

  if ! $DRUSH "${args[@]}" 2>&1 | tee "/tmp/xmt-install-${uri}.log" | tail -50; then
    echo "WARN: install reported failure for $uri — check /tmp/xmt-install-${uri}.log"
  fi

  if is_bootstrapped "$uri"; then
    echo "OK $uri"
  else
    echo "FAIL $uri not bootstrapped"
    return 1
  fi
}

install_one "xmt.pub" "芯媒体 XMT" "xmt_" || true
for row in \
  "zhubao.pub|珠宝媒体|zb_" \
  "airobotor.com|AI机器人|ar_" \
  "hm-os.com|鸿蒙OS|hm_" \
  "kstudy.com.cn|AI教育 KStudy|ks_" \
  "drupal.org.cn|Drupal中国|do_" \
  "itra.com.cn|ITRA中国|itra_"
do
  IFS='|' read -r uri name prefix <<<"$row"
  install_one "$uri" "$name" "$prefix" || true
done

echo "Admin login: $SITE_ADMIN_USER / $SITE_ADMIN_PASS"
echo "NOTE: D11.4 uses string table prefixes only; users are per-site (same credentials)."
