#!/bin/bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=/dev/null
source "$ROOT/scripts/00-env.sh"

# Ensure article fields on every site
ensure_fields() {
  local uri="$1"
  echo "==> Fields on $uri"
  $DRUSH --uri="$uri" php:script "$ROOT/scripts/ensure_fields.php" 2>&1 | tail -20 || \
  $DRUSH --uri="$uri" php:eval "
\\\$storage = \\Drupal::entityTypeManager()->getStorage('field_storage_config');
\\\$config = \\Drupal::entityTypeManager()->getStorage('field_config');
\\\$entity_type = 'node';
\\\$bundle = 'article';
\\\$defs = [
  'field_source_url' => ['type' => 'link', 'label' => 'Source URL'],
  'field_source_name' => ['type' => 'string', 'label' => 'Source Name'],
  'field_domain' => ['type' => 'string', 'label' => 'Domain Tag'],
];
foreach (\\\$defs as \\\$name => \\\$info) {
  if (!\\\$storage->load(\\\"\\\$entity_type.\\\$name\\\")) {
    \\\$storage->create([
      'field_name' => \\\$name,
      'entity_type' => \\\$entity_type,
      'type' => \\\$info['type'],
      'cardinality' => 1,
      'translatable' => FALSE,
    ])->save();
  }
  if (!\\\$config->load(\\\"\\\$entity_type.\\\$bundle.\\\$name\\\")) {
    \\\$config->create([
      'field_name' => \\\$name,
      'entity_type' => \\\$entity_type,
      'bundle' => \\\$bundle,
      'label' => \\\$info['label'],
      'required' => FALSE,
    ])->save();
  }
}
echo \"fields ok\\n\";
" 2>&1 | tail -20
}

for uri in xmt.pub zhubao.pub airobotor.com hm-os.com kstudy.com.cn drupal.org.cn itra.com.cn; do
  ensure_fields "$uri"
done

echo "==> Article fields ready"
