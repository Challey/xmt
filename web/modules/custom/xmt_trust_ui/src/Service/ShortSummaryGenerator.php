<?php

namespace Drupal\xmt_trust_ui\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Site\Settings;
use Drupal\node\NodeInterface;

/**
 * Builds short-read briefs and key points (extractive + optional AI).
 */
class ShortSummaryGenerator {

  public function __construct(
    protected CacheBackendInterface $cache,
  ) {}

  /**
   * Returns structured summary payload for a node.
   *
   * @return array{brief: string, keypoints: string[], body_text: string, reading_seconds: int, engine: string}
   */
  public function forNode(NodeInterface $node, bool $force = FALSE): array {
    $html = $this->nodeHtml($node);
    $cid = $this->cid($node->id(), $html);
    if (!$force && ($cached = $this->cache->get($cid))) {
      return $cached->data;
    }

    $payload = $this->build((string) $node->label(), $html);
    $this->cache->set($cid, $payload, time() + 86400, $node->getCacheTags());
    return $payload;
  }

  /**
   * Drop cached summary for a node and rebuild (may call AI if configured).
   *
   * @return array{brief: string, keypoints: string[], body_text: string, reading_seconds: int, engine: string}
   */
  public function regenerate(NodeInterface $node): array {
    $html = $this->nodeHtml($node);
    $this->cache->delete($this->cid($node->id(), $html));
    return $this->forNode($node, TRUE);
  }

  protected function nodeHtml(NodeInterface $node): string {
    $html = '';
    if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
      $html = (string) ($node->get('body')->value ?? '');
      $summary = (string) ($node->get('body')->summary ?? '');
      if ($summary !== '' && strlen(strip_tags($summary)) > 40) {
        $html = $summary . "\n" . $html;
      }
    }
    return $html;
  }

  protected function cid(int|string $nid, string $html): string {
    return 'xmt_short_v2:' . $nid . ':' . substr(hash('sha256', $html), 0, 12);
  }

  /**
   * Build summary from title + HTML.
   */
  public function build(string $title, string $html): array {
    $text = $this->normalizeText($html);
    $engine = 'extractive';

    $ai = $this->tryAi($title, $text);
    if ($ai) {
      $engine = 'ai';
      $brief = $ai['brief'];
      $keypoints = $ai['keypoints'];
    }
    else {
      $brief = $this->extractBrief($title, $text);
      $keypoints = $this->extractKeypoints($title, $text, $brief);
    }

    $chars = mb_strlen($text);
    // Prefer wall-clock skim time for short news (~12 chars/sec Chinese skim).
    $reading = max(6, min(45, (int) ceil(max($chars, mb_strlen($brief)) / 12)));

    return [
      'brief' => $brief,
      'keypoints' => $keypoints,
      'body_text' => $text,
      'reading_seconds' => $reading,
      'engine' => $engine,
    ];
  }

  protected function normalizeText(string $html): string {
    $html = preg_replace('#<script[\s\S]*?</script>#i', '', $html) ?? $html;
    $html = preg_replace('#<style[\s\S]*?</style>#i', '', $html) ?? $html;
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    // Drop trailing source boilerplate from agent imports.
    $text = preg_replace('/来源：\s*https?:\/\/\S+/u', '', $text) ?? $text;
    $text = preg_replace('/Source:\s*https?:\/\/\S+/iu', '', $text) ?? $text;
    return trim($text);
  }

  protected function extractBrief(string $title, string $text): string {
    if ($text === '') {
      return $title;
    }
    // Very short body: keep full text (often RSS summary only).
    if (mb_strlen($text) <= 200) {
      return $text;
    }
    // Prefer first sentence cluster under ~120 chars, expand to ~180.
    $parts = preg_split('/(?<=[。！？.!?])\s*/u', $text) ?: [$text];
    $brief = '';
    foreach ($parts as $p) {
      $p = trim($p);
      if ($p === '') {
        continue;
      }
      $next = $brief === '' ? $p : ($brief . $p);
      if (mb_strlen($next) > 180 && $brief !== '') {
        break;
      }
      $brief = $next;
      if (mb_strlen($brief) >= 90) {
        break;
      }
    }
    if ($brief === '') {
      $brief = mb_substr($text, 0, 160);
    }
    if (mb_strlen($text) > mb_strlen($brief) + 8) {
      $brief = rtrim($brief, '…') . '…';
    }
    return $brief;
  }

  /**
   * @return string[]
   */
  protected function extractKeypoints(string $title, string $text, string $brief = ''): array {
    $points = [];
    if ($text === '') {
      return $title !== '' ? [$title] : [];
    }
    $parts = preg_split('/(?<=[。！？.!?])\s*/u', $text) ?: [];
    foreach ($parts as $p) {
      $p = trim($p);
      if (mb_strlen($p) < 12) {
        continue;
      }
      // Skip near-duplicate of title.
      if ($title !== '' && mb_strpos($p, mb_substr($title, 0, min(10, mb_strlen($title)))) === 0 && mb_strlen($p) < mb_strlen($title) + 6) {
        continue;
      }
      // Skip sentences already covered by the brief.
      if ($brief !== '' && $this->isNearDuplicate($p, $brief)) {
        continue;
      }
      // Skip duplicates of already chosen points.
      $dup = FALSE;
      foreach ($points as $existing) {
        if ($this->isNearDuplicate($p, $existing)) {
          $dup = TRUE;
          break;
        }
      }
      if ($dup) {
        continue;
      }
      $points[] = mb_strlen($p) > 72 ? (mb_substr($p, 0, 70) . '…') : $p;
      if (count($points) >= 3) {
        break;
      }
    }
    // If everything was deduped away, offer a complementary slice after the brief.
    if ($points === [] && mb_strlen($text) > mb_strlen($brief) + 20) {
      $rest = trim(mb_substr($text, mb_strlen(rtrim($brief, '…'))));
      if ($rest !== '') {
        $points[] = mb_strlen($rest) > 70 ? (mb_substr($rest, 0, 70) . '…') : $rest;
      }
    }
    return $points;
  }

  /**
   * Loose similarity: substring or high character overlap on prefix.
   */
  protected function isNearDuplicate(string $a, string $b): bool {
    $a = trim($a);
    $b = trim($b);
    if ($a === '' || $b === '') {
      return FALSE;
    }
    if (mb_strpos($b, mb_substr($a, 0, min(24, mb_strlen($a)))) !== FALSE) {
      return TRUE;
    }
    if (mb_strpos($a, mb_substr($b, 0, min(24, mb_strlen($b)))) !== FALSE) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Optional OpenAI-compatible chat completion.
   *
   * Settings:
   * - xmt_ai_summary_url (e.g. https://api.deepseek.com/chat/completions)
   * - xmt_ai_summary_key
   * - xmt_ai_summary_model (default deepseek-chat)
   */
  protected function tryAi(string $title, string $text): ?array {
    $url = Settings::get('xmt_ai_summary_url');
    $key = Settings::get('xmt_ai_summary_key');
    if (!$url || !$key || $text === '') {
      return NULL;
    }
    $model = Settings::get('xmt_ai_summary_model', 'deepseek-chat');
    $prompt = "你是信媒体编辑。根据标题与正文，输出 JSON："
      . '{"brief":"80-120字中文摘要","keypoints":["要点1","要点2","要点3"]}。'
      . "只输出 JSON。\n标题：$title\n正文：" . mb_substr($text, 0, 2400);

    $payload = json_encode([
      'model' => $model,
      'messages' => [
        ['role' => 'user', 'content' => $prompt],
      ],
      'temperature' => 0.2,
    ], JSON_UNESCAPED_UNICODE);

    $ctx = stream_context_create([
      'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$key}\r\n",
        'content' => $payload,
        'timeout' => 12,
        'ignore_errors' => TRUE,
      ],
    ]);
    $raw = @file_get_contents((string) $url, FALSE, $ctx);
    if ($raw === FALSE) {
      return NULL;
    }
    $json = json_decode($raw, TRUE);
    $content = $json['choices'][0]['message']['content'] ?? '';
    if (!is_string($content) || $content === '') {
      return NULL;
    }
    if (preg_match('/\{[\s\S]*\}/', $content, $m)) {
      $content = $m[0];
    }
    $data = json_decode($content, TRUE);
    if (!is_array($data) || empty($data['brief'])) {
      return NULL;
    }
    $keypoints = [];
    if (!empty($data['keypoints']) && is_array($data['keypoints'])) {
      foreach ($data['keypoints'] as $k) {
        if (is_string($k) && trim($k) !== '') {
          $keypoints[] = trim($k);
        }
      }
    }
    $brief = trim((string) $data['brief']);
    // Deduplicate AI keypoints against brief.
    $keypoints = array_values(array_filter(
      $keypoints,
      fn(string $k): bool => !$this->isNearDuplicate($k, $brief),
    ));
    return [
      'brief' => $brief,
      'keypoints' => $keypoints ?: $this->extractKeypoints($title, $text, $brief),
    ];
  }

}
