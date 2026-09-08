<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sync short-news "read later" shelf for logged-in users.
 */
class ShortReadLaterSyncController extends ControllerBase {

  public function __construct(
    protected UserDataInterface $userData,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('user.data'),
    );
  }

  /**
   * GET later list.
   */
  public function get(): JsonResponse {
    $uid = (int) $this->currentUser()->id();
    if ($uid < 1) {
      return new JsonResponse(['error' => 'auth_required'], Response::HTTP_UNAUTHORIZED);
    }
    $items = $this->normalizeItems($this->userData->get('xmt_trust_ui', $uid, 'short_later'));
    return new JsonResponse([
      'schema' => 'xmt.short_read_later.v1',
      'uid' => $uid,
      'items' => $items,
      'count' => count($items),
    ]);
  }

  /**
   * POST merge/replace later items.
   */
  public function post(Request $request): JsonResponse {
    $uid = (int) $this->currentUser()->id();
    if ($uid < 1) {
      return new JsonResponse(['error' => 'auth_required'], Response::HTTP_UNAUTHORIZED);
    }
    try {
      $data = json_decode($request->getContent() ?: '{}', TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\Throwable $e) {
      return new JsonResponse(['error' => 'invalid_json'], Response::HTTP_BAD_REQUEST);
    }
    $incoming = $this->normalizeItems($data['items'] ?? []);
    $mode = ($data['mode'] ?? 'merge') === 'replace' ? 'replace' : 'merge';
    $existing = $this->normalizeItems($this->userData->get('xmt_trust_ui', $uid, 'short_later'));
    if ($mode === 'replace') {
      $merged = $incoming;
    }
    else {
      $by_nid = [];
      foreach (array_merge($incoming, $existing) as $item) {
        $by_nid[$item['nid']] = $item;
      }
      $merged = array_values($by_nid);
    }
    if (count($merged) > 80) {
      $merged = array_slice($merged, 0, 80);
    }
    $this->userData->set('xmt_trust_ui', $uid, 'short_later', $merged);
    return new JsonResponse([
      'schema' => 'xmt.short_read_later.v1',
      'uid' => $uid,
      'items' => $merged,
      'count' => count($merged),
      'saved' => TRUE,
    ]);
  }

  /**
   * @param mixed $raw
   *
   * @return list<array{nid: string, title: string, url: string}>
   */
  protected function normalizeItems($raw): array {
    if (!is_array($raw)) {
      return [];
    }
    $out = [];
    foreach ($raw as $row) {
      if (!is_array($row)) {
        continue;
      }
      $nid = preg_replace('/\D+/', '', (string) ($row['nid'] ?? ''));
      $title = trim((string) ($row['title'] ?? ''));
      $url = trim((string) ($row['url'] ?? ''));
      if ($nid === '' || $url === '') {
        continue;
      }
      if ($url !== '' && !preg_match('#^(/|https?://)#i', $url)) {
        continue;
      }
      $out[] = [
        'nid' => $nid,
        'title' => mb_substr($title !== '' ? $title : '短闻', 0, 200),
        'url' => mb_substr($url, 0, 500),
      ];
    }
    return $out;
  }

}
