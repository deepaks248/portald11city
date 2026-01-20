<?php

namespace Drupal\active_sessions\Service;

use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\global_module\Service\GlobalVariablesService;
use Drupal\global_module\Service\VaultConfigService;

class ActiveSessionService
{
    public const SECURE_URL = 'https://';
    public const BEARER = 'Bearer ';

    protected ClientInterface $httpClient;
    protected RequestStack $requestStack;
    protected LoggerInterface $logger;
    protected GlobalVariablesService $globalVariablesService;
    protected VaultConfigService $vaultConfigService;

    public function __construct(
        ClientInterface $httpClient,
        RequestStack $requestStack,
        LoggerInterface $logger,
        GlobalVariablesService $globalVariablesService,
        VaultConfigService $vaultConfigService
    ) {
        $this->httpClient = $httpClient;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
        $this->globalVariablesService = $globalVariablesService;
        $this->vaultConfigService = $vaultConfigService;
    }

    /**
     * Fetch active sessions using the access token.
     *
     * @param string $accessToken
     *   The OAuth2 access token.
     *
     * @return array|null
     *   Decoded JSON data or NULL on failure.
     */
    public function fetchActiveSessions(string $accessToken): ?array
    {
        $request = $this->requestStack->getCurrentRequest();

        // Get cookies from the request (optional if needed).
        $cookies = $request->headers->get('cookie');
        $idamconfig = $this->vaultConfigService->getGlobalVariables()['applicationConfig']['config']['idamconfig'];
        $url = self::SECURE_URL. $idamconfig .'/api/users/v1/me/sessions';
        try {
            $response = $this->httpClient->request('GET', $url , [
                'headers' => [
                    'Accept' => '*/*',
                    'Authorization' => self::BEARER . $accessToken,
                    'Cookie' => $cookies,
                ],
                'verify' => FALSE, // Only if self-signed cert; remove in production
            ]);

            $data = json_decode($response->getBody()->getContents(), TRUE);
            return $data ?? [];
        } catch (\Exception $e) {
            $this->logger->error('Error fetching active sessions: @message', ['@message' => $e->getMessage()]);
            return NULL;
        }
    }

    public function terminateSession(string $session_id, string $access_token): bool
    {
        $idamconfig = $this->vaultConfigService->getGlobalVariables()['applicationConfig']['config']['idamconfig'];
        $url = self::SECURE_URL . $idamconfig .'/api/users/v1/me/sessions/' . $session_id;

        try {
            $this->httpClient->request('DELETE', $url, [
                'headers' => [
                    'Accept' => '*/*',
                    'Authorization' => self::BEARER . $access_token,
                ],
                'verify' => FALSE, // if SSL cert is self-signed
            ]);

            return TRUE;
        } catch (RequestException $e) {
            $this->logger->error('Failed to terminate session: @message', ['@message' => $e->getMessage()]);
            return FALSE;
        }
    }

    public function terminateAllOtherSessions(string $access_token): bool
    {
        $idamconfig = $this->vaultConfigService->getGlobalVariables()['applicationConfig']['config']['idamconfig'];
        $url = self::SECURE_URL . $idamconfig .'/api/users/v1/me/sessions';

        try {
            $this->httpClient->request('DELETE', $url, [
                'headers' => [
                    'Accept' => '*/*',
                    'Authorization' => self::BEARER . $access_token,
                ],
                'verify' => FALSE, // if SSL cert is self-signed
            ]);

            return TRUE;
        } catch (RequestException $e) {
            $this->logger->error('Failed to terminate all other sessions: @message', ['@message' => $e->getMessage()]);
            return FALSE;
        }
    }
}
