<?php

namespace Drupal\login_logout\Service;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\global_module\Service\GlobalVariablesService;
// use Drupal\active_sessions\Service\ActiveSessionService;

class OAuthLoginService
{

    protected $httpClient;
    protected $logger;
    protected $requestStack;
    protected $globalVariablesService;
    // protected $activeSessionService;

    public function __construct(
        ClientInterface $http_client, 
        LoggerInterface $logger, 
        RequestStack $requestStack, 
        GlobalVariablesService $globalVariablesService
    )
    {
        $this->globalVariablesService = $globalVariablesService;
        // $this->activeSessionService = $activeSessionService;
        $this->httpClient = $http_client;
        $this->logger = $logger;
        $this->requestStack = $requestStack;
    }

    public function getFlowId(): ?string
    {
        try {
            $response = $this->httpClient->request('POST', 'https://tiotidam:9443/oauth2/authorize', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'client_id' => 'hVBu5NSpBJHJ84KF70nfQ8ZMdnQa',
                    'response_type' => 'code',
                    'redirect_uri' => 'https://cityportal.ddev.site/',
                    'scope' => 'openid internal_login',
                    'response_mode' => 'direct',
                ],
                'verify' => false,
            ]);

            $result = json_decode($response->getBody()->getContents(), TRUE);
            $this->logger->notice('Authorize response: <pre>@data</pre>', ['@data' => print_r($result, TRUE)]);
            return $result['flowId'] ?? NULL;
        } catch (\Exception $e) {
            $this->logger->error('Error getting Flow ID: @msg', ['@msg' => $e->getMessage()]);
            return NULL;
        }
    }

    public function authenticateUser(string $flow_id, string $email, string $password): ?string
    {
        $userAgent = $this->requestStack->getCurrentRequest()->headers->get('User-Agent');

        try {
            $payload = [
                "flowId" => $flow_id,
                "selectedAuthenticator" => [
                    "authenticatorId" => "QmFzaWNBdXRoZW50aWNhdG9yOkxPQ0FM",
                    "params" => [
                        "username" => $email,
                        "password" => $password,
                    ],
                ],
            ];

            $response = $this->httpClient->request('POST', 'https://tiotidam:9443/oauth2/authn', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'User-Agent' => $userAgent,
                ],
                'json' => $payload,
                'verify' => false,
            ]);

            $result = json_decode($response->getBody()->getContents(), TRUE);
            $this->logger->notice('Authn response: <pre>@data</pre>', ['@data' => print_r($result, TRUE)]);
            return $result['authData']['code'] ?? NULL;
        } catch (\Exception $e) {
            $this->logger->error('Error authenticating user: @msg', ['@msg' => $e->getMessage()]);
            return NULL;
        }
    }

    public function exchangeCodeForToken(string $code): ?array
    {
        try {
            $response = $this->httpClient->request('POST', 'https://tiotidam:9443/oauth2/token', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'client_id' => 'hVBu5NSpBJHJ84KF70nfQ8ZMdnQa',
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => 'https://cityportal.ddev.site/',
                    'code' => $code,
                ],
                'verify' => false,
            ]);

            $result = json_decode($response->getBody()->getContents(), TRUE);
            $this->logger->notice('Token response: <pre>@data</pre>', ['@data' => print_r($result, TRUE)]);
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Error exchanging code for token: @msg', ['@msg' => $e->getMessage()]);
            return NULL;
        }
    }

    public function checkEmailExists(string $email, string $access_token, string $api_url, string $api_version): bool
    {
        try {
            $full_url = $api_url . 'tiotcitizenapp' . $api_version . 'user/details';
            $response = $this->httpClient->request("POST", $full_url, [
                'json' => ['userId' => $email],
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                ],
            ]);
            $data = json_decode($response->getBody()->getContents(), TRUE);
            return !empty($data['data']);
        } catch (\Exception $e) {
            $this->logger->error('Error checking email: @msg', ['@msg' => $e->getMessage()]);
            return FALSE;
        }
    }

    public function logout(string $id_token_hint): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $cookies = $request->headers->get('cookie');

        try {
            $response = $this->httpClient->request('POST', 'https://tiotidam:9443/oidc/logout', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Cookie' => $cookies,
                ],
                'form_params' => [
                    'response_mode' => 'direct',
                    'id_token_hint' => $id_token_hint,
                ],
                'verify' => false, // 🚨 disables SSL verification
            ]);

            return [
                'status' => $response->getStatusCode(),
                'body' => (string) $response->getBody(),
            ];
        } catch (\Exception $e) {
            $this->logger->error('OIDC logout failed: @message', ['@message' => $e->getMessage()]);
            return [
                'status' => 500,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Full OAuth login flow (FlowId → Auth → Token).
     */
    public function performOAuthLogin(string $email, string $password): ?array
    {
        $flowId = $this->getFlowId();
        if (!$flowId) {
            throw new \Exception('Flow ID not received.');
        }

        $authorizationCode = $this->authenticateUser($flowId, $email, $password);
        if (!$authorizationCode) {
            throw new \Exception('Authorization code not received.');
        }

        return $this->exchangeCodeForToken($authorizationCode);
    }

    public function extractEmailFromJwt(string $idToken): ?string
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            return null;
        }
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        return $payload['sub'] ?? null;
    }

    /**
     * Validate email existence via external API.
     */
    public function validateEmail(string $email): bool
    {
        $accessToken = $this->globalVariablesService->getApimanAccessToken();
        $globals = $this->globalVariablesService->getGlobalVariables();

        $apiUrl = $globals['apiManConfig']['config']['apiUrl'] ?? '';
        $apiVersion = $globals['apiManConfig']['config']['apiVersion'] ?? '';

        return $this->checkEmailExists($email, $accessToken, $apiUrl, $apiVersion);
    }

    /**
     * Find closest matching session by login time.
     */
    // public function findClosestSessionId(string $accessToken, int $login_time): ?string
    // {
    //     $activeSessions = $this->activeSessionService->fetchActiveSessions($accessToken);

    //     $closestSessionId = null;
    //     $closestDiff = PHP_INT_MAX;
    //     $targetTimeMs = $login_time * 1000;

    //     if (!empty($activeSessions['sessions'])) {
    //         foreach ($activeSessions['sessions'] as $session) {
    //             if (!empty($session['loginTime'])) {
    //                 $diff = abs($session['loginTime'] - $targetTimeMs);
    //                 if ($diff < $closestDiff) {
    //                     $closestDiff = $diff;
    //                     $closestSessionId = $session['id'];
    //                 }
    //             }
    //         }
    //     }

    //     return $closestSessionId;
    // }
}
