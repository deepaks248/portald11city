<?php

namespace Drupal\global_module\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

class VaultConfigService
{

    public const CACHE_ID = 'vault_config_data';
    public const CACHE_TTL = 3600;
    public const APP_JSON = 'application/json';

    public function __construct(
        protected ClientInterface $httpClient,
        protected CacheBackendInterface $cache,
        protected LockBackendInterface $lock,
        protected LoggerInterface $logger
    ) {}

    /**
     * Fetch global configuration from Vault.
     */
    public function getGlobalVariables(): ?array
    {
        if ($cached = $this->getFromCache()) {
            return $cached;
        }

        if (!$this->acquireLock()) {
            return $this->getFromCache();
        }

        return $this->fetchAndCacheVaultData();
    }

    private function fetchAndCacheVaultData(): ?array
    {
        try {
            $vaultData = $this->fetchFromVault();

            if (!$vaultData) {
                return NULL;
            }

            $vaultData = $this->normalizeVaultData($vaultData);
            $this->storeInCache($vaultData);

            return $vaultData;
        } catch (\Throwable $e) {
            $this->logError($e);
            return NULL;
        } finally {
            $this->releaseLock();
        }
    }

    /* --------------------------------------------------------------------
   * Helper methods (reduce cognitive complexity)
   * ------------------------------------------------------------------ */

    private function getFromCache(): ?array
    {
        return $this->cache->get(self::CACHE_ID)->data ?? NULL;
    }

    private function storeInCache(array $data): void
    {
        $this->cache->set(
            self::CACHE_ID,
            $data,
            time() + self::CACHE_TTL
        );
    }

    private function acquireLock(): bool
    {
        if ($this->lock->acquire(self::CACHE_ID, 30)) {
            return TRUE;
        }

        usleep(100000);
        return FALSE;
    }

    private function releaseLock(): void
    {
        $this->lock->release(self::CACHE_ID);
    }

    private function fetchFromVault(): ?array
    {
        $vaultUrl = Settings::get('vault_url');
        $vaultToken = Settings::get('vault_token');

        if (!$vaultUrl || !$vaultToken) {
            $this->logger->error('Vault URL or token missing in settings.php');
            return NULL;
        }

        $response = $this->httpClient->request('GET', $vaultUrl, [
            'headers' => [
                'Content-Type' => self::APP_JSON,
                'X-Vault-Token' => $vaultToken,
            ],
        ]);

        $payload = json_decode($response->getBody()->getContents(), TRUE);

        return $payload['data'] ?? NULL;
    }

    private function normalizeVaultData(array $vaultData): array
    {
        $config = $vaultData['applicationConfig']['config'] ?? [];

        $vaultData['webportalUrl'] = $config['webportalUrl'] ?? '';
        $vaultData['siteUrl'] = $config['siteUrl'] ?? '';

        return $vaultData;
    }

    private function logError(\Throwable $e): void
    {
        $this->logger->error('Vault fetch failed: @message', [
            '@message' => $e->getMessage(),
        ]);
    }
}
