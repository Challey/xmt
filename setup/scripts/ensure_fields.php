<?php

/**
 * @file
 * Ensure article content type + syndication fields.
 * Usage: drush php:script setup/scripts/ensure_fields.php
 */

$entity_type = 'node';
$bundle = 'article';

$type_storage = \Drupal::entityTypeManager()->getStorage('node_type');
if (!$type_storage->load($bundle)) {
  $type_storage->create([
    'type' => $bundle,
    'name' => 'Article',
    'description' => 'Syndicated and editorial articles.',
    'new_revision' => TRUE,
    'preview_mode' => DRUPAL_OPTIONAL,
    'display_submitted' => TRUE,
  ])->save();
  echo "Created node type article\n";
}
else {
  echo "Node type article exists\n";
}

$storage = \Drupal::entityTypeManager()->getStorage('field_storage_config');
$config_storage = \Drupal::entityTypeManager()->getStorage('field_config');

$defs = [
  'field_source_url' => [
    'type' => 'link',
    'label' => 'Source URL',
    'settings' => [],
  ],
  'field_source_name' => [
    'type' => 'string',
    'label' => 'Source Name',
    'settings' => ['max_length' => 255],
  ],
  'field_domain' => [
    'type' => 'string',
    'label' => 'Domain Tag',
    'settings' => ['max_length' => 64],
  ],
];

foreach ($defs as $name => $info) {
  $storage_id = "$entity_type.$name";
  if (!$storage->load($storage_id)) {
    $values = [
      'field_name' => $name,
      'entity_type' => $entity_type,
      'type' => $info['type'],
      'cardinality' => 1,
      'translatable' => FALSE,
    ];
    if (!empty($info['settings'])) {
      $values['settings'] = $info['settings'];
    }
    $storage->create($values)->save();
    echo "Created storage $name\n";
  }
  else {
    echo "Storage exists $name\n";
  }

  $config_id = "$entity_type.$bundle.$name";
  if (!$config_storage->load($config_id)) {
    $config_storage->create([
      'field_name' => $name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'label' => $info['label'],
      'required' => FALSE,
      'translatable' => FALSE,
    ])->save();
    echo "Created instance $name on $bundle\n";
  }
  else {
    echo "Instance exists $name\n";
  }
}

// Body field (optional but useful for display).
$body_cfg = $config_storage->load('node.article.body');
if (!$body_cfg) {
  $fs = $storage->load('node.body');
  if (!$fs) {
    $storage->create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'type' => 'text_with_summary',
      'cardinality' => 1,
      'translatable' => TRUE,
      'settings' => [],
    ])->save();
    echo "Created storage body\n";
  }
  $config_storage->create([
    'field_name' => 'body',
    'entity_type' => 'node',
    'bundle' => 'article',
    'label' => 'Body',
    'required' => FALSE,
    'translatable' => TRUE,
  ])->save();
  echo "Created instance body on article\n";
}
else {
  echo "Instance exists body\n";
}

$form_display_storage = \Drupal::entityTypeManager()->getStorage('entity_form_display');
$form_display = $form_display_storage->load("node.$bundle.default");
if (!$form_display) {
  $form_display = $form_display_storage->create([
    'targetEntityType' => 'node',
    'bundle' => $bundle,
    'mode' => 'default',
    'status' => TRUE,
  ]);
}
foreach (array_keys($defs) as $name) {
  if (!$form_display->getComponent($name)) {
    $form_display->setComponent($name, [
      'type' => $name === 'field_source_url' ? 'link_default' : 'string_textfield',
      'weight' => 20,
    ]);
  }
}
if (!$form_display->getComponent('body')) {
  $form_display->setComponent('body', ['type' => 'text_textarea_with_summary', 'weight' => 10]);
}
if (!$form_display->getComponent('title')) {
  $form_display->setComponent('title', ['type' => 'string_textfield', 'weight' => -5]);
}
$form_display->save();

$view_display_storage = \Drupal::entityTypeManager()->getStorage('entity_view_display');
$view_display = $view_display_storage->load("node.$bundle.default");
if (!$view_display) {
  $view_display = $view_display_storage->create([
    'targetEntityType' => 'node',
    'bundle' => $bundle,
    'mode' => 'default',
    'status' => TRUE,
  ]);
}
foreach (array_keys($defs) as $name) {
  if (!$view_display->getComponent($name)) {
    $view_display->setComponent($name, [
      'type' => $name === 'field_source_url' ? 'link' : 'string',
      'label' => 'above',
      'weight' => 20,
    ]);
  }
}
if (!$view_display->getComponent('body')) {
  $view_display->setComponent('body', ['type' => 'text_default', 'label' => 'hidden', 'weight' => 0]);
}
$view_display->save();

$teaser = $view_display_storage->load("node.$bundle.teaser");
if (!$teaser) {
  $teaser = $view_display_storage->create([
    'targetEntityType' => 'node',
    'bundle' => $bundle,
    'mode' => 'teaser',
    'status' => TRUE,
  ]);
  $teaser->setComponent('body', [
    'type' => 'text_summary_or_trimmed',
    'label' => 'hidden',
    'settings' => ['trim_length' => 600],
    'weight' => 0,
  ]);
  $teaser->save();
  echo "Created teaser display\n";
}

echo "ensure_fields done\n";
