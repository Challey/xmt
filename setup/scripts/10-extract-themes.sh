#!/bin/bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=/dev/null
source "$ROOT/scripts/00-env.sh"

echo "==> Extracting Sancy & Kiamo themes/modules into $XMT_WEB"

TMP=/tmp/xmt-themes-extract
rm -rf "$TMP"
mkdir -p "$TMP"

unzip -qo /home/wwwroot/sancy-v11.3.zip -d "$TMP/sancy"
unzip -qo /home/wwwroot/kiamo-v11.3.zip -d "$TMP/kiamo"

mkdir -p "$XMT_WEB/themes/custom" "$XMT_WEB/modules/custom" "$XMT_WEB/libraries"

# Themes: prefer Drupal 11 update pack
cp -a "$TMP/sancy/files_update_theme_drupal_11/themes/gavias_sancy" "$XMT_WEB/themes/custom/"
cp -a "$TMP/kiamo/files_update_theme_drupal_11/themes/gavias_kiamo" "$XMT_WEB/themes/custom/"

# Custom Gavias modules from existing-installation
for m in gavias_content_builder gaviasthemer gavias_view gavias_views_magazine gavias_sancy_custom features_sancy; do
  src="$TMP/sancy/existing-installation/modules/custom/$m"
  if [ -d "$src" ]; then
    cp -a "$src" "$XMT_WEB/modules/custom/"
  fi
done
# kiamo-specific modules (overwrite custom hook if needed keep both)
for m in gavias_kiamo_custom features_kiamo; do
  src="$TMP/kiamo/existing-installation/modules/custom/$m"
  if [ -d "$src" ]; then
    cp -a "$src" "$XMT_WEB/modules/custom/"
  fi
done
# Shared gavias modules from kiamo if missing pieces
for m in gavias_content_builder gaviasthemer gavias_view gavias_views_magazine gavias_sliderlayer; do
  src="$TMP/kiamo/existing-installation/modules/custom/$m"
  if [ -d "$src" ] && [ ! -d "$XMT_WEB/modules/custom/$m" ]; then
    cp -a "$src" "$XMT_WEB/modules/custom/"
  fi
done

# Ensure theme core_version_requirement allows 11.4
for info in "$XMT_WEB/themes/custom/gavias_sancy/gavias_sancy.info.yml" \
            "$XMT_WEB/themes/custom/gavias_kiamo/gavias_kiamo.info.yml"; do
  if [ -f "$info" ]; then
    if ! grep -q 'core_version_requirement' "$info"; then
      echo 'core_version_requirement: ^10 || ^11' >> "$info"
    else
      sed -i 's/core_version_requirement:.*/core_version_requirement: ^10 || ^11/' "$info"
    fi
  fi
done

# Libraries from sancy existing-installation if present
if [ -d "$TMP/sancy/existing-installation/libraries" ]; then
  cp -a "$TMP/sancy/existing-installation/libraries/." "$XMT_WEB/libraries/" || true
fi
if [ -d "$TMP/kiamo/existing-installation/libraries" ]; then
  cp -a "$TMP/kiamo/existing-installation/libraries/." "$XMT_WEB/libraries/" || true
fi

echo "==> Themes/modules extracted"
ls "$XMT_WEB/themes/custom"
ls "$XMT_WEB/modules/custom" | head
