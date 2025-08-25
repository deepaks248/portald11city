<?php

namespace Drupal\active_sessions\Service;

use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Exception\RequestException;

class ActiveSessionService
{

    protected ClientInterface $httpClient;
    protected RequestStack $requestStack;
    protected LoggerInterface $logger;

    public function __construct(
        ClientInterface $httpClient,
        RequestStack $requestStack,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
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

        try {
            $response = $this->httpClient->request('GET', 'https://tiotidam:9443/api/users/v1/me/sessions', [
                'headers' => [
                    'Accept' => '*/*',
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Cookie' => $cookies,
                ],
                'verify' => false, // Only if self-signed cert; remove in production
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data ?? [];
        } catch (\Exception $e) {
            $this->logger->error('Error fetching active sessions: @message', ['@message' => $e->getMessage()]);
            return null;
        }
    }

    public function terminateSession(string $session_id, string $access_token): bool
    {
        $url = 'https://tiotidam:9443/api/users/v1/me/sessions/' . $session_id;

        try {
            $this->httpClient->request('DELETE', $url, [
                'headers' => [
                    'Accept' => '*/*',
                    'Authorization' => 'Bearer ' . $access_token,
                ],
                'verify' => false, // if SSL cert is self-signed
            ]);

            return true;
        } catch (RequestException $e) {
            $this->logger->error('Failed to terminate session: @message', ['@message' => $e->getMessage()]);
            return false;
        }
    }
}
