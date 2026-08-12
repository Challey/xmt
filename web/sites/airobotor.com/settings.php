<?php

$databases['default']['default'] = [
  'database' => 'xmt_multi',
  'username' => 'root',
  'password' => 'Pmg@123789',
  // Drupal 11.4+: prefix must be a string (per-table arrays removed).
  // Each site has its own tables; admin credentials are identical across sites.
  'prefix' => 'ar_',
  'host' => '192.168.16.1',
  'port' => '3306',
  'namespace' => 'Drupal\mysql\Driver\Database\mysql',
  'driver' => 'mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
  'charset' => 'utf8mb4',
  'collation' => 'utf8mb4_general_ci',
];

$settings['hash_salt'] = 'fpsh8yirxms3WON97V5mfDivMAVVxCV5XI527tZlV5rDYAgG9nplQCy';
$settings['config_sync_directory'] = '../config/sync/airobotor.com';
$settings['file_public_path'] = 'sites/airobotor.com/files';
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';
$settings['trusted_host_patterns'] = [
  '^.+$',
];
$config['system.logging']['error_level'] = 'verbose';
