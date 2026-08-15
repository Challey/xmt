<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sync short-news "already read" IDs for logged-in users.
 */
class ShortReadProgressController extends ControllerBase {

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
   * GET current user's read nids.
   */
  public function get(): JsonResponse {
    $uid = (int) $this->currentUser()->id();
    if ($uid < 1) {
      return new JsonResponse(['error' => 'auth_required'], Response::HTTP_UNAUTHORIZED);
    }
    $ids = $this->normalizeIds($this->userData->get('xmt_trust_ui', $uid, 'short_read_ids'));
    return new JsonResponse([
      'schema' => 'xmt.short_read_progress.v1',
      'uid' => $uid,
      'ids' => $ids,
      'count' => count($ids),
    ]);
  }

  /**
   * POST merge read nids (body: { "ids": ["1","2"], "mode": "merge"|"replace" }).
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
    $incoming = $this->normalizeIds($data['ids'] ?? []);
    $mode = ($data['mode'] ?? 'merge') === 'replace' ? 'replace' : 'merge';
    $existing = $this->normalizeIds($this->userData->get('xmt_trust_ui', $uid, 'short_read_ids'));
    $merged = $mode === 'replace' ? $incoming : array_values(array_unique(array_merge($incoming, $existing)));
    // Cap size; keep newest-ish by preserving incoming order first.
    if (count($merged) > 500) {
      $merged = array_slice($merged, 0, 500);
    }
    $this->userData->set('xmt_trust_ui', $uid, 'short_read_ids', $merged);
    return new JsonResponse([
      'schema' => 'xmt.short_read_progress.v1',
      'uid' => $uid,
      'ids' => $merged,
      'count' => count($merged),
      'saved' => TRUE,
    ]);
  }

  /**
   * @param mixed $raw
   *
   * @return list<string>
   */
  protected function normalizeIds($raw): array {
    if (!is_array($raw)) {
      return [];
    }
    $out = [];
    foreach ($raw as $id) {
      $id = preg_replace('/\D+/', '', (string) $id);
      if ($id !== '' && $id !== '0') {
        $out[] = $id;
      }
    }
    return array_values(array_unique($out));
  }

}
