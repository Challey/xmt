<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\xmt_trust_ui\DomainCatalog;
use Drupal\xmt_trust_ui\Service\ShortSummaryGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public RSS for short-news / trusted articles.
 */
class ShortNewsRssController extends ControllerBase {

  public function __construct(
    protected ShortSummaryGenerator $summarizer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('xmt_trust_ui.short_summary'),
    );
  }

  /**
   * RSS 2.0 feed.
   */
  public function feed(Request $request): Response {
    $level = (string) $request->query->get('level', 'trusted');
    $domain = trim((string) $request->query->get('domain', ''));
    $source = trim((string) $request->query->get('source', ''));
    $levels = match ($level) {
      'official', 'l1', 'l1_official' => ['l1_official'],
      'enterprise', 'l2', 'l2_enterprise' => ['l2_enterprise'],
      'aggregate', 'l0', 'l0_aggregate' => ['l0_aggregate'],
      default => ['l1_official', 'l2_enterprise', 'l0_aggregate'],
    };

    $storage = $this->entityTypeManager()->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', 1)
      ->condition('field_trust_level', $levels, 'IN')
      ->sort('created', 'DESC')
      ->range(0, 40);
    if ($domain !== '') {
      $values = DomainCatalog::expandFilter($domain);
      if (count($values) === 1) {
        $query->condition('field_domain', $values[0]);
      }
      else {
        $query->condition('field_domain', $values, 'IN');
      }
    }
    if ($source !== '') {
      $query->condition('field_source_name', $source);
    }
    $nids = $query->execute();

    $site = $this->config('system.site')->get('name') ?: 'XMT';
    $channel_link = Url::fromRoute('xmt_trust_ui.short_read', [], [
      'query' => array_filter([
        'level' => $level === 'trusted' ? NULL : $level,
        'domain' => $domain !== '' ? $domain : NULL,
        'source' => $source !== '' ? $source : NULL,
      ]),
      'absolute' => TRUE,
    ])->toString();
    $items_xml = '';
    $newest = 0;
    foreach ($storage->loadMultiple($nids ?: []) as $node) {
      if (!$node instanceof NodeInterface) {
        continue;
      }
      $newest = max($newest, (int) $node->getCreatedTime());
      $summary = $this->summarizer->forNode($node);
      $link = Url::fromRoute('xmt_trust_ui.short_read_detail', ['node' => $node->id()], ['absolute' => TRUE])->toString();
      $title = htmlspecialchars($node->label(), ENT_XML1 | ENT_QUOTES, 'UTF-8');
      $desc = htmlspecialchars($summary['brief'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
      $guid = htmlspecialchars($link, ENT_XML1 | ENT_QUOTES, 'UTF-8');
      $pub = gmdate(DATE_RSS, (int) $node->getCreatedTime());
      $items_xml .= "<item><title>{$title}</title><link>{$guid}</link><guid isPermaLink=\"true\">{$guid}</guid><pubDate>{$pub}</pubDate><description>{$desc}</description></item>";
    }

    $suffix = [];
    if ($level !== 'trusted') {
      $suffix[] = $level;
    }
    if ($domain !== '') {
      $suffix[] = $domain;
    }
    if ($source !== '') {
      $suffix[] = $source;
    }
    $title_extra = $suffix ? (' · ' . implode('/', $suffix)) : '';
    $channel_title = htmlspecialchars($site . ' · 短闻' . $title_extra, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $channel_desc = htmlspecialchars('可信短闻：摘要 + 官方原文入口' . $title_extra, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $self = Url::fromRoute('xmt_trust_ui.short_news_rss', [], [
      'query' => array_filter([
        'level' => $level === 'trusted' ? NULL : $level,
        'domain' => $domain !== '' ? $domain : NULL,
        'source' => $source !== '' ? $source : NULL,
      ]),
      'absolute' => TRUE,
    ])->toString();
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
      . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>'
      . "<title>{$channel_title}</title>"
      . '<link>' . htmlspecialchars($channel_link, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</link>'
      . '<atom:link rel="self" type="application/rss+xml" href="' . htmlspecialchars($self, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '"/>'
      . "<description>{$channel_desc}</description>"
      . '<language>zh-cn</language>'
      . $items_xml
      . '</channel></rss>';

    return new Response($xml, 200, [
      'Content-Type' => 'application/rss+xml; charset=utf-8',
      'Cache-Control' => 'public, max-age=120',
      'Last-Modified' => gmdate('D, d M Y H:i:s', $newest ?: time()) . ' GMT',
    ]);
  }

}
