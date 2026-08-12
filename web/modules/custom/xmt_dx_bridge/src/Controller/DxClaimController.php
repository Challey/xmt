<?php

namespace Drupal\xmt_dx_bridge\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xmt_dx_bridge\Service\DxClaimHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * REST endpoint for DrupalX publisher claims.
 */
class DxClaimController extends ControllerBase {

  public function __construct(
    protected DxClaimHandler $claimHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('xmt_dx_bridge.claim_handler'),
    );
  }

  /**
   * Accept and verify a signed claim.
   */
  public function claim(Request $request): JsonResponse {
    $body = $request->getContent();
    $signature = $request->headers->get('X-XMT-Signature');
    if (!$this->claimHandler->verifySignature($body, $signature)) {
      return new JsonResponse(['error' => 'Invalid signature'], Response::HTTP_FORBIDDEN);
    }

    try {
      $claim = json_decode($body, TRUE, 512, JSON_THROW_ON_ERROR);
      $pid = $this->claimHandler->processClaim($claim);
      return new JsonResponse([
        'status' => 'ok',
        'publisher_id' => $pid,
      ]);
    }
    catch (\Throwable $e) {
      return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
    }
  }

}
