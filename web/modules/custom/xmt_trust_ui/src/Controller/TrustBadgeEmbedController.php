<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\xmt_publisher\Entity\Publisher;
use Symfony\Component\HttpFoundation\Response;

/**
 * Embeddable trust badge HTML/JS for external sites.
 */
class TrustBadgeEmbedController extends ControllerBase {

  /**
   * Simple HTML badge for an article.
   */
  public function nodeHtml(int $nid): Response {
    $node = $this->entityTypeManager()->getStorage('node')->load($nid);
    if (!$node instanceof NodeInterface || !$node->isPublished() || !$node->access('view')) {
      return new Response('Not found', 404);
    }
    $level = $node->hasField('field_trust_level') && !$node->get('field_trust_level')->isEmpty()
      ? (string) $node->get('field_trust_level')->value
      : 'l0_aggregate';
    $label = xmt_trust_badge_label($level);
    $class = xmt_trust_badge_class($level);
    $url = $node->toUrl('canonical', ['absolute' => TRUE])->toString();
    $short = Url::fromRoute('xmt_trust_ui.short_read_detail', ['node' => $nid], ['absolute' => TRUE])->toString();
    $api = Url::fromRoute('xmt_trust_ui.badge_node', ['nid' => $nid], ['absolute' => TRUE])->toString();
    $html = $this->renderBadgeHtml($label, $class, $short, $api, $node->label());
    $rss = Url::fromRoute('xmt_trust_ui.short_news_rss', [], ['absolute' => TRUE])->toString();
    $today = Url::fromRoute('xmt_trust_ui.short_read_today', [], ['absolute' => TRUE])->toString();
    $extra = '<p style="margin:.75rem 0 0;font:13px/1.5 system-ui,sans-serif;">'
      . '<a href="' . htmlspecialchars($short, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" rel="noopener noreferrer" target="_blank">短闻</a>'
      . ' · '
      . '<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" rel="noopener noreferrer" target="_blank">站点原文</a>'
      . ' · '
      . '<a href="' . htmlspecialchars(Url::fromRoute('xmt_trust_ui.short_read', [], ['absolute' => TRUE])->toString(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" rel="noopener noreferrer" target="_blank">短闻首页</a>'
      . ' · '
      . '<a href="' . htmlspecialchars($today, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" rel="noopener noreferrer" target="_blank">今日简报</a>'
      . ' · '
      . '<a href="' . htmlspecialchars($rss, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" rel="alternate noopener noreferrer" target="_blank" type="application/rss+xml">RSS</a>'
      . '</p>';
    return $this->htmlResponse($html . $extra);
  }

  /**
   * Simple HTML badge for a publisher.
   */
  public function publisherHtml(int $xmt_publisher): Response {
    $entity = $this->entityTypeManager()->getStorage('xmt_publisher')->load($xmt_publisher);
    if (!$entity instanceof Publisher || !$entity->access('view')) {
      return new Response('Not found', 404);
    }
    $type = (string) $entity->get('type')->value;
    $level = $type === 'official' ? 'l1_official' : 'l2_enterprise';
    if ($entity->get('status')->value !== 'approved') {
      $level = 'l0_aggregate';
    }
    $label = xmt_trust_badge_label($level);
    $class = xmt_trust_badge_class($level);
    $url = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
    $api = Url::fromRoute('xmt_trust_ui.badge_publisher', ['xmt_publisher' => $xmt_publisher], ['absolute' => TRUE])->toString();
    $html = $this->renderBadgeHtml($label, $class, $url, $api, $entity->label());
    return $this->htmlResponse($html);
  }

  /**
   * Tiny JS loader: fetch badge from API.
   */
  public function nodeScript(int $nid): Response {
    $api = Url::fromRoute('xmt_trust_ui.badge_node', ['nid' => $nid], ['absolute' => TRUE])->toString();
    return new Response($this->loaderScript($api), 200, [
      'Content-Type' => 'application/javascript; charset=utf-8',
      'Cache-Control' => 'public, max-age=300',
      'Access-Control-Allow-Origin' => '*',
    ]);
  }

  /**
   * Loader JS for publisher badge.
   */
  public function publisherScript(int $xmt_publisher): Response {
    $api = Url::fromRoute('xmt_trust_ui.badge_publisher', ['xmt_publisher' => $xmt_publisher], ['absolute' => TRUE])->toString();
    return new Response($this->loaderScript($api), 200, [
      'Content-Type' => 'application/javascript; charset=utf-8',
      'Cache-Control' => 'public, max-age=300',
      'Access-Control-Allow-Origin' => '*',
    ]);
  }

  protected function renderBadgeHtml(string $label, string $class, string $url, string $api, string $title): string {
    $safe_label = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safe_class = htmlspecialchars($class, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safe_url = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safe_api = htmlspecialchars($api, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safe_title = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return <<<HTML
<a class="xmt-embed-badge {$safe_class}" href="{$safe_url}" title="{$safe_title}" data-xmt-api="{$safe_api}" rel="noopener noreferrer" target="_blank" style="display:inline-flex;align-items:center;gap:.35rem;padding:.2rem .55rem;border:1px solid #ccc;border-radius:4px;font:12px/1.4 system-ui,sans-serif;text-decoration:none;color:#111;background:#fff;">
  <span style="font-weight:700;">XMT</span>
  <span>{$safe_label}</span>
</a>
HTML;
  }

  protected function htmlResponse(string $html): Response {
    $body = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>XMT Badge</title></head><body style="margin:0;padding:8px;">'
      . $html . '</body></html>';
    return new Response($body, 200, [
      'Content-Type' => 'text/html; charset=utf-8',
      'Cache-Control' => 'public, max-age=60',
      'Access-Control-Allow-Origin' => '*',
    ]);
  }

  protected function loaderScript(string $api): string {
    $api_js = json_encode($api, JSON_UNESCAPED_SLASHES);
    return <<<JS
(function(){
  var api = {$api_js};
  var s = document.currentScript;
  var target = document.createElement('span');
  target.className = 'xmt-badge-slot';
  if (s && s.parentNode) { s.parentNode.insertBefore(target, s.nextSibling); }
  fetch(api, {credentials:'omit'}).then(function(r){return r.json();}).then(function(d){
    if (!d || d.error) { target.textContent = 'XMT'; return; }
    var a = document.createElement('a');
    a.href = d.short_read_url || d.url || '#';
    a.target = '_blank';
    a.rel = 'noopener noreferrer';
    a.className = 'xmt-embed-badge ' + (d.badge_class || '');
    a.title = d.title || d.name || 'XMT';
    a.style.cssText = 'display:inline-flex;align-items:center;gap:.35rem;padding:.2rem .55rem;border:1px solid #ccc;border-radius:4px;font:12px/1.4 system-ui,sans-serif;text-decoration:none;color:#111;background:#fff;';
    a.innerHTML = '<span style="font-weight:700;">XMT</span><span>' + (d.badge_label || 'Trust') + '</span>';
    target.appendChild(a);
    if (d.short_read_url) {
      var more = document.createElement('a');
      more.href = d.short_read_url;
      more.target = '_blank';
      more.rel = 'noopener noreferrer';
      more.textContent = '短闻';
      more.style.cssText = 'margin-left:.5rem;font:12px/1.4 system-ui,sans-serif;';
      target.appendChild(more);
    }  }).catch(function(){ target.textContent = 'XMT'; });
})();
JS;
  }

}
