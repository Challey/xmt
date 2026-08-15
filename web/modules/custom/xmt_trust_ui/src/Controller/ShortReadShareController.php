<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Open Graph / share card image for short news.
 */
class ShortReadShareController extends ControllerBase {

  /**
   * SVG share card (1200×630).
   */
  public function svg(NodeInterface $node): Response {
    if ($node->bundle() !== 'article' || !$node->isPublished() || !$node->access('view')) {
      throw new NotFoundHttpException();
    }

    $title = $this->truncate((string) $node->label(), 48);
    $level = $node->hasField('field_trust_level') && !$node->get('field_trust_level')->isEmpty()
      ? (string) $node->get('field_trust_level')->value
      : 'l0_aggregate';
    $badge = function_exists('xmt_trust_badge_label')
      ? (string) xmt_trust_badge_label($level)
      : $level;
    $source = '';
    if ($node->hasField('field_source_name') && !$node->get('field_source_name')->isEmpty()) {
      $source = $this->truncate((string) $node->get('field_source_name')->value, 28);
    }

    $title_xml = htmlspecialchars($title, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $badge_xml = htmlspecialchars($badge, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $source_xml = htmlspecialchars($source, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $lines = $this->wrapLines($title, 22);
    $text_nodes = '';
    $y = 250;
    foreach (array_slice($lines, 0, 3) as $i => $line) {
      $text_nodes .= '<text x="72" y="' . ($y + $i * 58) . '" class="title">' . htmlspecialchars($line, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</text>';
    }

    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#1a222b"/>
      <stop offset="55%" stop-color="#24303c"/>
      <stop offset="100%" stop-color="#3a2a1a"/>
    </linearGradient>
    <style>
      .brand { font: 700 36px "Source Han Serif SC","Noto Serif SC",serif; fill: #f4efe6; }
      .badge { font: 600 22px "PingFang SC","Noto Sans SC",sans-serif; fill: #1a1208; }
      .title { font: 700 44px "Source Han Serif SC","Noto Serif SC",serif; fill: #f7f2ea; }
      .meta { font: 400 24px "PingFang SC","Noto Sans SC",sans-serif; fill: #c9c0b3; }
      .foot { font: 500 20px "PingFang SC","Noto Sans SC",sans-serif; fill: #9a9186; }
    </style>
  </defs>
  <rect width="1200" height="630" fill="url(#bg)"/>
  <rect x="0" y="0" width="12" height="630" fill="#c8843a"/>
  <text x="72" y="88" class="brand">XMT · 短闻</text>
  <rect x="72" y="118" rx="8" ry="8" width="{$this->badgeWidth($badge)}" height="40" fill="#c8843a"/>
  <text x="88" y="146" class="badge">{$badge_xml}</text>
  {$text_nodes}
  <text x="72" y="520" class="meta">{$source_xml}</text>
  <text x="72" y="575" class="foot">可信分层 · RSS 官方收录 · xmt.pub/read</text>
</svg>
SVG;

    $response = new Response($svg, 200, [
      'Content-Type' => 'image/svg+xml; charset=utf-8',
      'Cache-Control' => 'public, max-age=3600',
    ]);
    $response->setPublic();
    return $response;
  }

  /**
   * Approximate badge pill width.
   */
  protected function badgeWidth(string $badge): int {
    return max(120, 40 + mb_strlen($badge) * 18);
  }

  /**
   * Truncate with ellipsis.
   */
  protected function truncate(string $text, int $max): string {
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if (mb_strlen($text) <= $max) {
      return $text;
    }
    return mb_substr($text, 0, $max - 1) . '…';
  }

  /**
   * Soft-wrap by character count for CJK titles.
   *
   * @return list<string>
   */
  protected function wrapLines(string $text, int $width): array {
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $lines = [];
    $buf = '';
    foreach ($chars as $ch) {
      $buf .= $ch;
      if (mb_strlen($buf) >= $width) {
        $lines[] = $buf;
        $buf = '';
      }
    }
    if ($buf !== '') {
      $lines[] = $buf;
    }
    return $lines ?: [''];
  }

}
