#!/bin/bash
# Verify XMT trust platform endpoints after deploy (local WSL or Server B).
# Usage:
#   bash setup/scripts/75-verify-trust.sh
#   HOST=xmt.pub BASE=https://xmt.pub bash setup/scripts/75-verify-trust.sh
#   HOST=xmt.pub BASE=http://127.0.0.1 DRUSH_URI=xmt.pub bash setup/scripts/75-verify-trust.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=/dev/null
source "$ROOT/scripts/00-env.sh"

HOST="${HOST:-xmt.pub}"
BASE="${BASE:-http://127.0.0.1}"
DRUSH_URI="${DRUSH_URI:-xmt.pub}"
FAIL=0

pass() { echo "  OK  $1"; }
fail() { echo "  FAIL $1"; FAIL=1; }

curl_check() {
  local path="$1"
  local pattern="${2:-}"
  local code
  code=$(curl -sS -o /tmp/xmt-verify-body.txt -w '%{http_code}' -H "Host: ${HOST}" "${BASE}${path}" || echo "000")
  if [[ "$code" != "200" ]]; then
    fail "${path} HTTP ${code}"
    return
  fi
  if [[ -n "$pattern" ]] && ! grep -qE "$pattern" /tmp/xmt-verify-body.txt; then
    fail "${path} missing pattern: ${pattern}"
    return
  fi
  pass "${path}"
}

echo "==> XMT trust verify (Host=${HOST}, BASE=${BASE})"

echo "-- HTTP pages"
curl_check "/trusted" "xmt-trust-feed|可信"
curl_check "/trusted/feed.json" '"feed"'
curl_check "/trusted/official/feed.rss" "<rss"
curl_check "/publishers" "xmt-publishers-directory|publishers"
curl_check "/trusted/sitemap.xml" "<urlset"
curl_check "/robots.txt" "Sitemap:.*trusted/sitemap"

echo "-- Publisher page (id=1, skip if missing)"
PUB_CODE=$(curl -sS -o /tmp/xmt-verify-pub.txt -w '%{http_code}' -H "Host: ${HOST}" "${BASE}/publisher/1" || echo "000")
if [[ "$PUB_CODE" == "200" ]]; then
  if grep -qE "xmt-publisher|xmt-trust-badge" /tmp/xmt-verify-pub.txt; then
    pass "/publisher/1"
    curl_check "/publisher/1/feed.json" '"feed"'
  else
    fail "/publisher/1 content"
  fi
else
  echo "  SKIP /publisher/1 HTTP ${PUB_CODE}"
fi

if [[ -x "$DRUSH" ]]; then
  echo "-- Drush (${DRUSH_URI})"
  if $DRUSH --uri="$DRUSH_URI" status --fields=bootstrap 2>/dev/null | grep -q Successful; then
    pass "drush status"
  else
    fail "drush status"
  fi
  if $DRUSH --uri="$DRUSH_URI" xmt:provenance-export --limit=1 --output=/tmp/xmt-provenance-verify.csv 2>/dev/null; then
    if [[ -s /tmp/xmt-provenance-verify.csv ]]; then
      pass "xmt:provenance-export"
    else
      fail "xmt:provenance-export empty"
    fi
  else
    fail "xmt:provenance-export"
  fi
else
  echo "  SKIP drush (not found at $DRUSH)"
fi

echo ""
if [[ "$FAIL" -eq 0 ]]; then
  echo "==> All checks passed"
  exit 0
fi
echo "==> Some checks failed"
exit 1
