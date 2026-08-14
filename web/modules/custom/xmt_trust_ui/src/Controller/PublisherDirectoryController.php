<?php

namespace Drupal\xmt_trust_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xmt_trust_ui\Service\PublisherPageBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Public directory of certified publishers.
 */
class PublisherDirectoryController extends ControllerBase {

  public function __construct(
    protected PublisherPageBuilder $pageBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('xmt_trust_ui.publisher_page_builder'),
    );
  }

  /**
   * Lists approved official and enterprise publishers.
   */
  public function list(): array {
    return $this->pageBuilder->buildDirectory();
  }

}
