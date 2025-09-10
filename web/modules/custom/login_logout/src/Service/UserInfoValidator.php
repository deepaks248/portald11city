<?php

namespace Drupal\login_logout\Service;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class UserInfoValidator {

  protected $httpClient;
  protected $logger;
  protected $session;

  public function __construct(ClientInterface $http_client, LoggerInterface $logger, SessionInterface $session) {
    $this->httpClient = $http_client;
    $this->logger = $logger;
    $this->session = $session;
  }

  /**
   * Validate the current session's access token with /userinfo.
   *
   * @return array|null
   *   Returns decoded user info if valid, or NULL if invalid.
   */
  public function validate() {
    $accessToken = $this->session->get('login_logout.access_token');

    if (!$accessToken) {
      $this->logger->notice('No access token found in session.');
      return NULL;
    }

    try {
      $response = $this->httpClient->request('POST', 'https://hcsjointstacknew.trinityiot.in/oauth2/userinfo', [
        'headers' => [
          'Content-Type'  => 'application/x-www-form-urlencoded',
          'Authorization' => 'Bearer ' . $accessToken,
        ],
        'verify' => false,
      ]);

      $data = json_decode($response->getBody()->getContents(), TRUE);

      if (!empty($data['sub'])) {
        return $data;
      }
      else {
        $this->logger->warning('UserInfo check failed: no sub returned.');
        return NULL;
      }
    }
    catch (\Exception $e) {
      $this->logger->error('UserInfo validation error: @msg', ['@msg' => $e->getMessage()]);
      return NULL;
    }
  }

}
