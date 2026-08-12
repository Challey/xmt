<?php

namespace Drupal\xmt_dx_bridge\Commands;

use Drupal\xmt_dx_bridge\Service\DxClaimHandler;
use Drush\Commands\DrushCommands;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

/**
 * Drush commands for testing the DrupalX bridge locally.
 */
class DxBridgeCommands extends DrushCommands {

  public function __construct(
    protected DxClaimHandler $claimHandler,
  ) {
    parent::__construct();
  }

  /**
   * Simulate a local DX claim POST to xmt.pub.
   *
   * @param array $options
   *   Command options.
   *
   * @command xmt:dx-claim-test
   * @option developer DrupalX developer ID.
   * @option name Publisher display name.
   * @option target-uri Target site URI (Drush site name, e.g. xmt.pub).
   * @usage drush xmt:dx-claim-test --developer=DX123 --name="Test Corp"
   */
  public function claimTest(array $options = [
    'developer' => 'dx-test-001',
    'name' => 'Test Enterprise',
    'target-uri' => 'xmt.pub',
  ]): void {
    $secret = $this->claimHandler->getSecret();
    if ($secret === '') {
      throw new \RuntimeException('Set xmt_dx_bridge_secret or XMT_DX_BRIDGE_SECRET first.');
    }

    $claim = [
      'publisher_name' => $options['name'],
      'credit_code' => '91110000TEST0000X',
      'website' => 'https://example.com',
      'dx_developer_id' => $options['developer'],
      'exp' => time() + 3600,
      'nonce' => bin2hex(random_bytes(8)),
    ];
    $body = json_encode($claim, JSON_UNESCAPED_UNICODE);
    $signature = hash_hmac('sha256', $body, $secret);

    $hostHeader = $options['target-uri'] === 'xmt.pub' ? 'xmt.wsl' : $options['target-uri'];
    $url = 'http://127.0.0.1/api/xmt/v1/dx-claim';
    $client = new Client(['http_errors' => FALSE, 'timeout' => 30]);
    $response = $client->post($url, [
      RequestOptions::BODY => $body,
      RequestOptions::HEADERS => [
        'Content-Type' => 'application/json',
        'X-XMT-Signature' => $signature,
        'Host' => $hostHeader,
      ],
    ]);

    $this->output()->writeln((string) $response->getBody());
    if ($response->getStatusCode() >= 400) {
      throw new \RuntimeException('Claim test failed with HTTP ' . $response->getStatusCode());
    }
    $this->logger()->success('Claim accepted.');
  }

}
