<?php

namespace Drupal\xmt_syndicate\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\NodeInterface;

/**
 * Syndicates article nodes into the xmt.pub table prefix.
 */
class SyndicateService {

  public function __construct(
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Site URI used for aggregation.
   */
  public const XMT_URI = 'xmt.pub';

  /**
   * Publish or update a mirror article on xmt.pub via Drush in a subprocess.
   *
   * Cross-prefix entity API is unreliable; we shell out to the xmt site URI.
   */
  public function syndicateToXmt(NodeInterface $node): void {
    $current = \Drupal::request()?->getHost() ?? '';
    if (str_contains($current, 'xmt.pub') || str_contains($current, 'xmt.wsl')) {
      return;
    }

    $source_url = '';
    if ($node->hasField('field_source_url') && !$node->get('field_source_url')->isEmpty()) {
      $source_url = $node->get('field_source_url')->uri ?? $node->get('field_source_url')->value ?? '';
    }
    $source_name = '';
    if ($node->hasField('field_source_name') && !$node->get('field_source_name')->isEmpty()) {
      $source_name = $node->get('field_source_name')->value;
    }
    $domain = '';
    if ($node->hasField('field_domain') && !$node->get('field_domain')->isEmpty()) {
      $domain = $node->get('field_domain')->value;
    }

    $payload = [
      'title' => $node->label(),
      'body' => $node->hasField('body') ? ($node->get('body')->value ?? '') : '',
      'format' => $node->hasField('body') ? ($node->get('body')->format ?? 'basic_html') : 'basic_html',
      'source_url' => $source_url,
      'source_name' => $source_name,
      'domain' => $domain,
      'origin_nid' => (int) $node->id(),
      'origin_site' => $current,
    ];

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $tmp = tempnam(sys_get_temp_dir(), 'xmt_syn_');
    file_put_contents($tmp, $json);

    $root = dirname(\Drupal::root());
    $drush = $root . '/vendor/bin/drush';
    if (!is_executable($drush)) {
      $drush = 'drush';
    }
    $cmd = escapeshellcmd($drush) . ' --uri=' . escapeshellarg(self::XMT_URI)
      . ' xmt:import-article ' . escapeshellarg($tmp) . ' 2>&1';
    exec($cmd, $out, $code);
    @unlink($tmp);
    $this->loggerFactory->get('xmt_syndicate')->notice('Syndicate to xmt: code=@c out=@o', [
      '@c' => $code,
      '@o' => implode("\n", array_slice($out, -5)),
    ]);
  }

  /**
   * Create or update article from payload on current site.
   */
  public function importArticle(array $data): int {
    $storage = $this->entityTypeManager->getStorage('node');
    $source_url = $data['source_url'] ?? '';
    $nid = NULL;

    if ($source_url !== '' && $this->fieldExists('node', 'article', 'field_source_url')) {
      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'article')
        ->condition('field_source_url', $source_url)
        ->range(0, 1)
        ->execute();
      if ($ids) {
        $nid = (int) reset($ids);
      }
    }

    if ($nid) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = $storage->load($nid);
    }
    else {
      $node = $storage->create([
        'type' => 'article',
        'uid' => 1,
        'status' => 1,
      ]);
    }

    $node->setTitle($data['title'] ?? 'Untitled');
    if ($node->hasField('body')) {
      $node->set('body', [
        'value' => $data['body'] ?? '',
        'format' => $data['format'] ?? 'full_html',
      ]);
    }
    if ($node->hasField('field_source_url') && !empty($data['source_url'])) {
      $node->set('field_source_url', ['uri' => $data['source_url']]);
    }
    if ($node->hasField('field_source_name')) {
      $node->set('field_source_name', $data['source_name'] ?? '');
    }
    if ($node->hasField('field_domain')) {
      $node->set('field_domain', $data['domain'] ?? '');
    }
    if ($node->hasField('field_trust_level') && !empty($data['trust_level'])) {
      $node->set('field_trust_level', $data['trust_level']);
    }
    $node->xmt_skip_syndicate = TRUE;
    $node->save();
    return (int) $node->id();
  }

  protected function fieldExists(string $entity_type, string $bundle, string $field_name): bool {
    $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type, $bundle);
    return isset($fields[$field_name]);
  }

}
