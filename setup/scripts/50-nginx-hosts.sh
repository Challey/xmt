#!/bin/bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=/dev/null
source "$ROOT/scripts/00-env.sh"

# LNMP: nginx proxies PHP to Apache :88.
# Templates live in setup/nginx and setup/apache.
if [ "$(id -u)" -eq 0 ]; then
  bash "$ROOT/install-nginx-as-root.sh"
else
  echo "==> Nginx/Apache vhost templates in $ROOT/nginx and $ROOT/apache"
  echo "==> Apply as root:"
  echo "    wsl.exe -u root -e bash $ROOT/install-nginx-as-root.sh"
fi
