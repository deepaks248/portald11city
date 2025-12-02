<?php

namespace Drupal\active_sessions\Service;

use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\global_module\Service\GlobalVariablesService;

class ActiveSessionService
{

    protected ClientInterface $httpClient;
    protected RequestStack $requestStack;
    protected LoggerInterface $logger;
    protected GlobalVariablesService $globalVariablesService;

    public function __construct(
        ClientInterface $httpClient,
        RequestStack $requestStack,
        LoggerInterface $logger,
        GlobalVariablesService $globalVariablesService
    ) {
        $this->httpClient = $httpClient;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
        $this->globalVariablesService = $globalVariablesService;
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
        $bearer = 'Bearer';
        try {
            $idamconfig = $this->globalVariablesService->getGlobalVariables()['applicationConfig']['config']['idamconfig'];
            $response = $this->httpClient->request('GET', 'https://'. $idamconfig .':/api/users/v1/me/sessions', [
                'headers' => [
                    'Accept' => '*/*',
                    'Authorization' => $bearer . $accessToken,
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
        $idamconfig = $this->globalVariablesService->getGlobalVariables()['applicationConfig']['config']['idamconfig'];
        $url = 'https://'. $idamconfig .'/api/users/v1/me/sessions/' . $session_id;
        $bearer = 'Bearer';
        try {
            $this->httpClient->request('DELETE', $url, [
                'headers' => [
                    'Accept' => '*/*',
                    'Authorization' => $bearer . $access_token,
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
        $idamconfig = $this->globalVariablesService->getGlobalVariables()['applicationConfig']['config']['idamconfig'];
        $url = 'https://'. $idamconfig .'/api/users/v1/me/sessions';
        $bearer = 'Bearer';
        try {
            $this->httpClient->request('DELETE', $url, [
                'headers' => [
                    'Accept' => '*/*',
                    'Authorization' => $bearer . $access_token,
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
