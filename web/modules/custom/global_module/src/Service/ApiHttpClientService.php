<?php

namespace Drupal\global_module\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

class ApiHttpClientService
{

    public const APP_JSON = 'application/json';
    public const BEARER = 'Bearer ';

    public function __construct(
        protected ClientInterface $httpClient,
        protected LoggerInterface $logger,
        protected ApimanTokenService $apimanTokenService
    ) {}

    /* ------------------------------------------------------------------------
   * PUBLIC WRAPPERS (Very low cognitive complexity)
   * --------------------------------------------------------------------- */

    public function postApiman(string $url, array $payload = [], string $method = 'POST'): array
    {
        return $this->request($method, $url, [
            'headers' => $this->apimanHeaders(),
            'json' => $payload,
        ]);
    }

    public function deleteApiman(string $url): array
    {
        return $this->request('DELETE', $url, [
            'headers' => $this->apimanHeaders(),
        ]);
    }

    public function postIdam(string $url, array $payload = [], string $method = 'POST'): array
    {
        return $this->request($method, $url, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => $payload,
            'verify' => false,
        ]);
    }

    public function postIdamAuth(string $url, array $payload = [], string $method = 'POST'): array
    {
        return $this->request($method, $url, [
            'headers' => [
                'Accept' => self::APP_JSON,
                'Authorization' => 'Basic ' . base64_encode('trinity:trinity@123'),
            ],
            'json' => $payload,
            'verify' => false,
        ], 'idam_auth');
    }

    public function postApi(string $url): ?array
    {
        return $this->request('POST', $url, [
            'headers' => ['Accept' => self::APP_JSON],
            'verify' => false,
        ]);
    }

    public function getApi(string $url): ?array
    {
        return $this->request('GET', $url, [
            'headers' => [
                'Accept' => self::APP_JSON,
                'Authorization' => 'Basic ' . base64_encode('trinity:trinity@123'),
            ],
            'verify' => false,
        ]);
    }

    /* ------------------------------------------------------------------------
   * CORE REQUEST HANDLER (ONLY place with try/catch)
   * --------------------------------------------------------------------- */

    private function request(
        string $method,
        string $url,
        array $options = [],
        string $logChannel = 'global_module'
    ): ?array {
        try {
            $response = $this->httpClient->request(
                strtoupper($method),
                $url,
                $options
            );

            return json_decode(
                $response->getBody()->getContents(),
                true
            );
        } catch (RequestException $e) {
            $this->logException($e, $logChannel);
            return ['error' => 'Request failed'];
        } catch (\Exception $e) {
            $this->logger->error(
                'HTTP request failed: @message',
                ['@message' => $e->getMessage()]
            );
            return null;
        }
    }

    /* ------------------------------------------------------------------------
   * HELPERS
   * --------------------------------------------------------------------- */

    private function apimanHeaders(): array
    {
        return [
            'Content-Type' => self::APP_JSON,
            'Accept' => self::APP_JSON,
            'Authorization' => self::BEARER . $this->apimanTokenService->getApimanAccessToken(),
        ];
    }

    private function logException(RequestException $e, string $channel): void
    {
        $responseBody = $e->hasResponse()
            ? (string) $e->getResponse()->getBody()
            : null;

        $this->logger->error(
            'HTTP request failed: @message | Response: @response',
            [
                '@message' => $e->getMessage(),
                '@response' => $responseBody,
            ]
        );
    }
}
