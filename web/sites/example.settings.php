<?php

/**
 * @file
 * Example settings for an XMT multisite entry (copy to sites/{domain}/settings.php).
 */

$databases['default']['default'] = [
  'database' => 'xmt_multi',
  'username' => 'root',
  'password' => 'CHANGE_ME',
  'prefix' => 'xmt_',
  'host' => '127.0.0.1',
  'port' => '3306',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'driver' => 'mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
  'charset' => 'utf8mb4',
  'collation' => 'utf8mb4_general_ci',
];

$settings['hash_salt'] = 'GENERATE_A_LONG_RANDOM_STRING';
$settings['config_sync_directory'] = '../config/sync/xmt.pub';
$settings['file_public_path'] = 'sites/xmt.pub/files';
$settings['trusted_host_patterns'] = ['^.+$'];

// DrupalX bridge shared secret (also set in DrupalX .env).
$settings['xmt_dx_bridge_secret'] = getenv('XMT_DX_BRIDGE_SECRET') ?: '';
