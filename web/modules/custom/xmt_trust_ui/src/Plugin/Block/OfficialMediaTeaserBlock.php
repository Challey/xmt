<?php

namespace Drupal\xmt_trust_ui\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\xmt_trust_ui\OfficialMediaChannels;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Homepage teaser for 官媒报道.
 *
 * @Block(
 *   id = "xmt_official_media_teaser",
 *   admin_label = @Translation("XMT Official Media Teaser"),
 *   category = @Translation("XMT")
 * )
 */
class OfficialMediaTeaserBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', 1)
      ->condition('field_trust_level', 'l1_official')
      ->condition('field_domain', array_keys(OfficialMediaChannels::CHANNELS), 'IN')
      ->sort('created', 'DESC')
      ->range(0, 8)
      ->execute();

    $items = [];
    if ($nids) {
      foreach ($storage->loadMultiple($nids) as $node) {
        $domain = $node->get('field_domain')->value ?? '';
        $items[] = [
          'url' => Url::fromRoute('xmt_trust_ui.short_read_detail', ['node' => $node->id()])->toString(),
          'title' => $node->label(),
          'channel_label' => OfficialMediaChannels::label($domain),
        ];
      }
    }

    $channels = [];
    foreach (OfficialMediaChannels::CHANNELS as $code => $label) {
      $channels[] = [
        'label' => $label,
        'url' => Url::fromRoute('xmt_trust_ui.official_media_channel', ['channel' => $code])->toString(),
      ];
    }

    return [
      '#theme' => 'xmt_official_media_teaser',
      '#items' => $items,
      '#channels' => $channels,
      '#more_url' => Url::fromRoute('xmt_trust_ui.official_media')->toString(),
      '#short_news_url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
        'query' => ['level' => 'official'],
      ])->toString(),
      '#today_url' => Url::fromRoute('xmt_trust_ui.short_read_today', [], [
        'query' => ['level' => 'official'],
      ])->toString(),
      '#rss_url' => Url::fromRoute('xmt_trust_ui.short_news_rss', [], [
        'query' => ['level' => 'official'],
      ])->toString(),
      '#attached' => ['library' => ['xmt_trust_ui/trust_feed', 'xmt_trust_ui/short_read']],
      '#cache' => [
        'tags' => ['node_list'],
        'contexts' => ['languages:language_interface'],
      ],
    ];
  }

}
