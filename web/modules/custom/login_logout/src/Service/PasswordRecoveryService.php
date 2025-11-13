<?php

namespace Drupal\login_logout\Service;

use GuzzleHttp\ClientInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\global_module\Service\GlobalVariablesService;

/**
 * Service for handling password recovery related API calls.
 */
class PasswordRecoveryService {

  /**
   * HTTP client service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Global variables service.
   *
   * @var \Drupal\global_module\Service\GlobalVariablesService
   */
  protected $globalVariablesService;

  /**
   * Constructs a PasswordRecoveryService object.
   */
  public function __construct(
    ClientInterface $http_client,
    GlobalVariablesService $globalVariablesService,
    LoggerChannelInterface $logger
  ) {
    $this->httpClient = $http_client;
    $this->globalVariablesService = $globalVariablesService;
    $this->logger = $logger;
  }

  /**
   * Initiate password recovery process.
   *
   * @param string $email
   *   The email address of the user.
   *
   * @return string|null
   *   The recovery code or NULL on failure.
   */
  public function initiateRecovery(string $email): ?string {
    $idamconfig = $this->globalVariablesService->getGlobalVariables()['applicationConfig']['config']['idamconfig'];
    $url = 'https://' . $idamconfig . '/api/users/v2/recovery/password/init';

    $payload = [
      'claims' => [
        [
          'uri' => 'http://wso2.org/claims/emailaddress',
          'value' => $email,
        ],
      ],
      'properties' => [
        [
          'key' => 'key',
          'value' => 'value',
        ],
      ],
    ];

    $headers = [
      'accept' => 'application/json',
      'Content-Type' => 'application/json',
      'Authorization' => 'Basic YWRtaW46VHJpbml0eUAxMjM=',
    ];

    try {
      $response = $this->httpClient->request('POST', $url, [
        'headers' => $headers,
        'json' => $payload,
        'timeout' => 10,
      ]);
      $decoded = json_decode($response->getBody()->getContents(), TRUE);

      if (!empty($decoded[0]['channelInfo']['recoveryCode'])) {
        return $decoded[0]['channelInfo']['recoveryCode'];
      }

      $this->logger->warning('No recovery code returned for email: @email', ['@email' => $email]);
      return NULL;
    }
    catch (RequestException $e) {
      $this->logger->error('Password recovery initiation failed: @error', ['@error' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Complete password recovery using recovery code.
   *
   * @param string $recovery_code
   *   The recovery code received in the email or SMS.
   * @param string $channel_id
   *   The channel ID (usually "1" or "2" depending on medium).
   *
   * @return array|null
   *   The decoded JSON response or NULL on failure.
   */
  public function completeRecovery(string $recovery_code, string $channel_id = '1'): ?array {

    $idamconfig = $this->globalVariablesService->getGlobalVariables()['applicationConfig']['config']['idamconfig'];
    $url = 'https://' . $idamconfig . '/api/users/v2/recovery/password/recover';

    $payload = [
      'recoveryCode' => $recovery_code,
      'channelId' => $channel_id,
      'properties' => [
        [
          'key' => 'key',
          'value' => 'value',
        ],
      ],
    ];

    $headers = [
      'accept' => 'application/json',
      'Content-Type' => 'application/json',
      'Authorization' => 'Basic dHJpbml0eTp0cmluaXR5QDEyMw==',
    ];

    try {
      $response = $this->httpClient->request('POST', $url, [
        'headers' => $headers,
        'json' => $payload,
      ]);

      $decoded = json_decode($response->getBody()->getContents(), TRUE);
      $this->logger->info('Password recovery completed successfully for recovery code: @code', ['@code' => $recovery_code]);
      return $decoded;
    }
    catch (RequestException $e) {
      $this->logger->error('Password recovery completion failed: @error', ['@error' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Combined method: Initiate and complete password recovery.
   *
   * @param string $email
   *   The user's email.
   *
   * @return array|null
   *   The final response from the completeRecovery() call.
   */
  public function recoverPassword(string $email): ?array {
    try {
      // Step 1: Initiate recovery to get the recovery code.
      $recovery_code = $this->initiateRecovery($email);
        if (empty($recovery_code)) {
            $this->logger->warning('Password recovery initiation failed for email: @email', ['@email' => $email]);
            return NULL;
        }
        
        // Step 2: Complete recovery using the code.
        $response = $this->completeRecovery($recovery_code, '1');
        
      return $response;
    }
    catch (\Exception $e) {
      $this->logger->error('Password recovery process failed: @msg', ['@msg' => $e->getMessage()]);
      return NULL;
    }
  }

}