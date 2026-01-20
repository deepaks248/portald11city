<?php

namespace Drupal\global_module\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

class ApimanTokenService {

  public const CACHE_ID = 'apiman_access_token';
  public const EXPIRY_BUFFER = 30;

  public function __construct(
    protected ClientInterface $httpClient,
    protected CacheBackendInterface $cache,
    protected VaultConfigService $vaultConfigService,
    protected LoggerInterface $logger
  ) {}

  /**
   * Get Apiman access token.
   */
  public function getApimanAccessToken(): ?string {
    if ($token = $this->getCachedToken()) {
      return $token;
    }

    $config = $this->getApimanConfig();
    if (!$config) {
      return NULL;
    }

    return $this->fetchAndCacheToken($config);
  }

  /* --------------------------------------------------------------------
   * Helper methods (reduce cognitive complexity)
   * ------------------------------------------------------------------ */

  private function getCachedToken(): ?string {
    $cached = $this->cache->get(self::CACHE_ID)->data ?? NULL;

    if (
      is_array($cached) &&
      !empty($cached['access_token']) &&
      time() < ($cached['expires_at'] ?? 0)
    ) {
      return $cached['access_token'];
    }

    return NULL;
  }

  private function getApimanConfig(): ?array {
    $globals = $this->vaultConfigService->getGlobalVariables();
    $config = $globals['apiManConfig']['config'] ?? NULL;

    if (!$config) {
      $this->logger->error('Missing apiManConfig configuration in Vault response.');
    }

    return $config;
  }

  private function fetchAndCacheToken(array $config): ?string {
    try {
      $response = $this->httpClient->request(
        'POST',
        $this->buildTokenUrl($config),
        $this->buildRequestOptions($config)
      );

      $data = json_decode($response->getBody()->getContents(), TRUE);

      return $this->cacheToken($data);
    }
    catch (RequestException $e) {
      $this->logger->error('Apiman token fetch failed: @message', [
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  private function buildTokenUrl(array $config): string {
    return $config['apiUrl']
      . 'tiotAPIESBSubSystem'
      . $config['apiVersion']
      . 'getAccessToken';
  }

  private function buildRequestOptions(array $config): array {
    return [
      'headers' => ['Content-Type' => 'application/json'],
      'body' => json_encode($config),
      'verify' => FALSE,
    ];
  }

  private function cacheToken(?array $data): ?string {
    if (
      empty($data['access_token']) ||
      empty($data['expires_in'])
    ) {
      return NULL;
    }

    $expiresAt = time() + $data['expires_in'] - self::EXPIRY_BUFFER;

    $this->cache->set(
      self::CACHE_ID,
      [
        'access_token' => $data['access_token'],
        'expires_at' => $expiresAt,
      ],
      time() + $data['expires_in']
    );

    return $data['access_token'];
  }

}
