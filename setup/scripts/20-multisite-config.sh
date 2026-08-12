#!/bin/bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=/dev/null
source "$ROOT/scripts/00-env.sh"

echo "==> Configuring multisite directories & sites.php"

mkdir -p "$XMT_WEB/sites"

# sites.php
cat > "$XMT_WEB/sites/sites.php" <<'PHP'
<?php

/**
 * @file
 * Multisite directory aliasing for XMT network.
 */

$sites['xmt.pub'] = 'xmt.pub';
$sites['www.xmt.pub'] = 'xmt.pub';
$sites['xmt.wsl'] = 'xmt.pub';

$sites['zhubao.pub'] = 'zhubao.pub';
$sites['www.zhubao.pub'] = 'zhubao.pub';
$sites['zhubao.wsl'] = 'zhubao.pub';

$sites['airobotor.com'] = 'airobotor.com';
$sites['www.airobotor.com'] = 'airobotor.com';
$sites['airobotor.wsl'] = 'airobotor.com';

$sites['hm-os.com'] = 'hm-os.com';
$sites['www.hm-os.com'] = 'hm-os.com';
$sites['hm-os.cn'] = 'hm-os.com';
$sites['www.hm-os.cn'] = 'hm-os.com';
$sites['hmos.wsl'] = 'hm-os.com';

$sites['kstudy.com.cn'] = 'kstudy.com.cn';
$sites['www.kstudy.com.cn'] = 'kstudy.com.cn';
$sites['kstudy.wsl'] = 'kstudy.com.cn';

$sites['drupal.org.cn'] = 'drupal.org.cn';
$sites['www.drupal.org.cn'] = 'drupal.org.cn';
$sites['drupalcn.wsl'] = 'drupal.org.cn';

$sites['itra.com.cn'] = 'itra.com.cn';
$sites['www.itra.com.cn'] = 'itra.com.cn';
$sites['itra.wsl'] = 'itra.com.cn';
PHP

write_settings() {
  local dir="$1"
  local prefix="$2"
  local files_path="${3:-}"
  mkdir -p "$XMT_WEB/sites/$dir/files"
  chmod 777 "$XMT_WEB/sites/$dir/files" || true
  local hash
  hash=$(php -r 'echo substr(str_replace(["/","+"],"A",base64_encode(random_bytes(55))),0,55);')

  cat > "$XMT_WEB/sites/$dir/settings.php" <<PHP
<?php

\$databases['default']['default'] = [
  'database' => '${MYSQL_DATABASE}',
  'username' => '${MYSQL_USER}',
  'password' => '${MYSQL_PASSWORD}',
  // Drupal 11.4+: prefix must be a string (per-table arrays removed).
  // Each site has its own tables; admin credentials are identical across sites.
  'prefix' => '${prefix}',
  'host' => '${MYSQL_HOST}',
  'port' => '${MYSQL_PORT}',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'driver' => 'mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
  'charset' => 'utf8mb4',
  'collation' => 'utf8mb4_general_ci',
];

\$settings['hash_salt'] = '${hash}';
\$settings['config_sync_directory'] = '../config/sync/${dir}';
\$settings['file_public_path'] = 'sites/${dir}/files';
\$settings['container_yamls'][] = \$app_root . '/' . \$site_path . '/services.yml';
\$settings['trusted_host_patterns'] = [
  '^.+\$',
];
\$config['system.logging']['error_level'] = 'verbose';
PHP

  mkdir -p "$XMT_ROOT/config/sync/$dir"
}

# Map: directory|prefix
write_settings "xmt.pub" "xmt_"
write_settings "zhubao.pub" "zb_"
write_settings "airobotor.com" "ar_"
write_settings "hm-os.com" "hm_"
write_settings "kstudy.com.cn" "ks_"
write_settings "drupal.org.cn" "do_"
write_settings "itra.com.cn" "itra_"

# Keep default pointing to xmt for CLI fallback
if [ ! -f "$XMT_WEB/sites/default/settings.php" ]; then
  mkdir -p "$XMT_WEB/sites/default/files"
  cp "$XMT_WEB/sites/xmt.pub/settings.php" "$XMT_WEB/sites/default/settings.php"
fi

echo "==> Multisite settings written"
