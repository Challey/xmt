<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\xmt_trust_ui\OfficialMediaChannels;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 官媒报道 listing (L1 + channel domain).
 */
class OfficialMediaController extends ControllerBase {

  /**
   * All official-media articles or one channel.
   */
  public function listing(?string $channel = NULL): array {
    if ($channel !== NULL && !OfficialMediaChannels::isValid($channel)) {
      throw new NotFoundHttpException();
    }

    $storage = $this->entityTypeManager()->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', 1)
      ->condition('field_trust_level', 'l1_official')
      ->condition('field_domain', array_keys(OfficialMediaChannels::CHANNELS), 'IN')
      ->sort('created', 'DESC')
      ->range(0, 40);

    if ($channel) {
      $query->condition('field_domain', $channel);
    }

    $nids = $query->execute();
    $items = [];
    $date_formatter = \Drupal::service('date.formatter');
    if ($nids) {
      foreach ($storage->loadMultiple($nids) as $node) {
        $domain = $node->hasField('field_domain') ? ($node->get('field_domain')->value ?? '') : '';
        $source = $node->hasField('field_source_name') ? ($node->get('field_source_name')->value ?? '') : '';
        $items[] = [
          'url' => Url::fromRoute('xmt_trust_ui.short_read_detail', ['node' => $node->id()])->toString(),
          'immerse_url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
            'query' => array_filter([
              'level' => 'official',
              'domain' => $domain !== '' ? $domain : NULL,
              'mode' => 'immerse',
              'focus' => $node->id(),
            ]),
          ])->toString(),
          'title' => $node->label(),
          'date' => $date_formatter->format($node->getCreatedTime(), 'custom', 'Y-m-d H:i'),
          'channel' => $domain,
          'channel_label' => OfficialMediaChannels::label($domain),
          'source' => $source,
          'badge' => xmt_trust_badge_label('l1_official'),
          'badge_class' => xmt_trust_badge_class('l1_official'),
        ];
      }
    }

    $channels = [];
    foreach (OfficialMediaChannels::CHANNELS as $code => $label) {
      $channels[] = [
        'code' => $code,
        'label' => $label,
        'url' => Url::fromRoute('xmt_trust_ui.official_media_channel', ['channel' => $code])->toString(),
        'active' => $channel === $code,
        'short_url' => Url::fromRoute('xmt_trust_ui.short_read', [], [
          'query' => [
            'level' => 'official',
            'domain' => $code,
          ],
        ])->toString(),
      ];
    }

    $short_query = array_filter([
      'level' => 'official',
      'domain' => $channel ?: NULL,
    ]);

    return [
      '#theme' => 'xmt_official_media',
      '#title_text' => $channel
        ? $this->t('官媒报道 · @ch', ['@ch' => OfficialMediaChannels::label($channel)])
        : $this->t('官媒报道'),
      '#all_url' => Url::fromRoute('xmt_trust_ui.official_media')->toString(),
      '#short_news_url' => Url::fromRoute('xmt_trust_ui.short_read', [], ['query' => $short_query])->toString(),
      '#today_url' => Url::fromRoute('xmt_trust_ui.short_read_today', [], ['query' => $short_query])->toString(),
      '#rss_url' => Url::fromRoute('xmt_trust_ui.short_news_rss', [], ['query' => $short_query])->toString(),
      '#channel' => $channel,
      '#channels' => $channels,
      '#items' => $items,
      '#attached' => ['library' => ['xmt_trust_ui/trust_feed', 'xmt_trust_ui/short_read']],
      '#cache' => [
        'tags' => ['node_list'],
        'contexts' => ['url.path', 'languages:language_interface'],
      ],
    ];
  }

  /**
   * Dynamic title for channel pages.
   */
  public function channelTitle(string $channel): string {
    if (!OfficialMediaChannels::isValid($channel)) {
      return (string) $this->t('官媒报道');
    }
    return (string) $this->t('官媒报道 · @ch', ['@ch' => OfficialMediaChannels::label($channel)]);
  }

}
