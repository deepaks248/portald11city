<?php

namespace Drupal\login_logout\Service;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\global_module\Service\GlobalVariablesService;
use Drupal\Core\Site\Settings;
use Drupal\global_module\Service\VaultConfigService;
use Drupal\global_module\Service\ApimanTokenService;

class OAuthLoginService
{
    public const SECURE_LINK = 'https://';
    public const APP_JSON = 'application/json';
    public const FORM_URLENCODED = 'application/x-www-form-urlencoded';
    protected $httpClient;
    protected $logger;
    protected $requestStack;
    protected $globalVariablesService;
    protected $vaultConfigService;
    protected $apimanTokenService;

    public function __construct(
        ClientInterface $http_client,
        LoggerInterface $logger,
        RequestStack $requestStack,
        GlobalVariablesService $globalVariablesService,
        VaultConfigService $vaultConfigService,
        ApimanTokenService $apimanTokenService
    ) {
        $this->globalVariablesService = $globalVariablesService;
        $this->httpClient = $http_client;
        $this->logger = $logger;
        $this->requestStack = $requestStack;
        $this->apimanTokenService = $apimanTokenService;
        $this->vaultConfigService = $vaultConfigService;
    }

    public function getFlowId(): ?string
    {
        try {
            $idamconfig = $this->vaultConfigService->getGlobalVariables()['applicationConfig']['config']['idamconfig'];
            $response = $this->httpClient->request('POST', self::SECURE_LINK . $idamconfig . '/oauth2/authorize', [
                'headers' => [
                    'Accept' => self::APP_JSON,
                    'Content-Type' => self::FORM_URLENCODED,
                ],
                'form_params' => [
                    'client_id' => 'hVBu5NSpBJHJ84KF70nfQ8ZMdnQa',
                    'response_type' => 'code',
                    'redirect_uri' => 'https://cityportal.ddev.site/',
                    'scope' => 'openid internal_login',
                    'response_mode' => 'direct',
                ],
                'verify' => FALSE,
            ]);

            $result = json_decode($response->getBody()->getContents(), TRUE);
            $this->logger->notice('Authorize response: <pre>@data</pre>', ['@data' => print_r($result, TRUE)]);
            return $result['flowId'] ?? NULL;
        } catch (\Exception $e) {
            $this->logger->error('Error getting Flow ID: @msg', ['@msg' => $e->getMessage()]);
            return NULL;
        }
    }

    public function format_user_agent(string $userAgent): string
    {
        $browser = $this->detectFromRules($userAgent, [
            'Microsoft Edge' => ['Edg'],
            'Chrome'         => ['Chrome', '!Chromium'],
            'Firefox'        => ['Firefox'],
            'Safari'         => ['Safari', '!Chrome'],
            'Opera'          => ['Opera', 'OPR'],
        ], 'Unknown Browser');

        $device = $this->detectFromRules($userAgent, [
            'Desktop (Windows)' => ['Windows'],
            'Desktop (Mac)'     => ['Macintosh', 'Mac OS X'],
            'Mobile (iPhone)'   => ['iPhone'],
            'Tablet (iPad)'     => ['iPad'],
            'Mobile (Android)'  => ['Android', 'Mobile'],
            'Tablet (Android)'  => ['Android'],
            'Linux'             => ['Linux'],
        ], 'Unknown Device/OS');

        if ($browser === 'Unknown Browser' && $device === 'Unknown Device/OS') {
            return $userAgent;
        }

        return "{$browser}, {$device}";
    }

    private function detectFromRules(string $agent, array $rules, string $default): string
    {
        foreach ($rules as $label => $conditions) {
            if ($this->matchesConditions($agent, (array) $conditions)) {
                return $label;
            }
        }

        return $default;
    }

    private function matchesConditions(string $agent, array $conditions): bool
    {
        foreach ($conditions as $condition) {
            $negate = $condition[0] === '!';
            $token  = ltrim($condition, '!');

            $found = stripos($agent, $token) !== FALSE;

            if ($negate && $found) {
                return FALSE;
            }

            if (!$negate && !$found) {
                return FALSE;
            }
        }

        return TRUE;
    }

    public function authenticateUser(string $flow_id, string $email, string $password): ?array
    {
        $userAgent = $this->requestStack->getCurrentRequest()->headers->get('User-Agent');
        $payload = $this->prepareAuthPayload($flow_id, $email, $password);
        $idamconfig = $this->getIdamConfig();

        try {
            $response = $this->sendAuthenticationRequest($idamconfig, $payload, $userAgent);
            $result = $this->parseResponse($response);

            if ($this->isAuthSuccess($result)) {
                return $this->handleAuthSuccess($result);
            }

            if ($this->isActiveSessionLimitReached($result)) {
                return $this->handleSessionLimit($result, $email);
            }

            return $this->handleErrorResponse($result);
        } catch (\Exception $e) {
            $this->logError($e->getMessage());
            return $this->generateErrorResponse();
        }
    }

    private function prepareAuthPayload($flow_id, $email, $password)
    {
        return [
            "flowId" => $flow_id,
            "selectedAuthenticator" => [
                "authenticatorId" => Settings::get('idam_local_authenticator_id'),
                "params" => ["username" => $email, "password" => $password],
            ],
        ];
    }

    private function sendAuthenticationRequest($idamconfig, $payload, $userAgent)
    {
        return $this->httpClient->request('POST', self::SECURE_LINK . $idamconfig . '/oauth2/authn', [
            'headers' => [
                'Accept' => self::APP_JSON,
                'Content-Type' => self::APP_JSON,
                'User-Agent' => $userAgent,
            ],
            'json' => $payload,
            'verify' => FALSE,
        ]);
    }

    private function parseResponse($response)
    {
        return json_decode($response->getBody()->getContents(), TRUE);
    }

    private function isAuthSuccess($result)
    {
        return !empty($result['authData']['code']);
    }

    private function handleAuthSuccess($result)
    {
        return ['success' => TRUE, 'code' => $result['authData']['code'], 'message' => NULL];
    }

    private function isActiveSessionLimitReached($result)
    {
        return !empty($result['nextStep']['authenticators'][0]['authenticator']) &&
            $result['nextStep']['authenticators'][0]['authenticator'] === 'Active Sessions Limit';
    }

    private function handleSessionLimit($result, $email)
    {
        $maxSessions = $result['nextStep']['authenticators'][0]['metadata']['additionalData']['MaxSessionCount'] ?? 'unknown';
        $sessions = json_decode($result['nextStep']['authenticators'][0]['metadata']['additionalData']['sessions'] ?? '[]', TRUE);
        $sessionList = $this->formatSessions($sessions);

        $this->logger->notice('Active sessions for user @email: @sessions', [
            '@email' => $email,
            '@sessions' => $sessionList,
        ]);

        return [
            'success' => FALSE,
            'code' => NULL,
            'message' => "You have reached the maximum active sessions ($maxSessions).",
        ];
    }

    private function formatSessions($sessions)
    {
        $sessionList = '';
        foreach ($sessions as $s) {
            $sessionList .= "- Browser: {$s['browser']}, Device: {$s['device']}, Last Active: " .
                date('Y-m-d H:i:s', $s['lastAccessTime'] / 1000) . "\n";
        }
        return $sessionList;
    }

    private function handleErrorResponse($result)
    {
        if (!empty($result['nextStep']['messages'][0]['message'])) {
            return [
                'success' => FALSE,
                'code' => NULL,
                'message' => $result['nextStep']['messages'][0]['message'],
            ];
        }
        return ['success' => FALSE, 'code' => NULL, 'message' => 'Authentication failed. Please try again.'];
    }

    private function logError($message)
    {
        $this->logger->error('Error authenticating user: @msg', ['@msg' => $message]);
    }

    private function generateErrorResponse()
    {
        return [
            'success' => FALSE,
            'code' => NULL,
            'message' => 'An error occurred during authentication. Please try again later.',
        ];
    }


    /**
     * Decode a JWT token payload.
     *
     * @param string $jwt
     *   The JWT token (e.g., id_token).
     *
     * @return array|null
     *   Returns the payload as an associative array, or NULL if invalid.
     */
    public function decodeJwt(string $jwt): ?array
    {
        if (!$this->isValidJwtFormat($jwt)) {
            return NULL; // Invalid token format
        }

        $payload = $this->extractPayloadFromJwt($jwt);
        return $this->decodeBase64Url($payload);
    }

    private function isValidJwtFormat($jwt)
    {
        return count(explode('.', $jwt)) === 3;
    }

    private function extractPayloadFromJwt($jwt)
    {
        return explode('.', $jwt)[1];
    }

    private function decodeBase64Url($payload)
    {
        // Decode Base64Url (replace -_ with +/ and pad with =)
        $payload = strtr($payload, '-_', '+/');
        $mod4 = strlen($payload) % 4;
        if ($mod4) {
            $payload .= str_repeat('=', 4 - $mod4);
        }

        return json_decode(base64_decode($payload), TRUE);
    }

    public function exchangeCodeForToken(string $code): ?array
    {
        try {
            $idamconfig = $this->getIdamConfig();
            $response = $this->sendTokenRequest($idamconfig, $code);
            return $this->parseResponse($response);
        } catch (\Exception $e) {
            $this->logError($e->getMessage());
            return NULL;
        }
    }

    private function sendTokenRequest($idamconfig, $code)
    {
        return $this->httpClient->request('POST', self::SECURE_LINK . $idamconfig . '/oauth2/token', [
            'headers' => ['Content-Type' => self::FORM_URLENCODED],
            'form_params' => [
                'client_id' => 'hVBu5NSpBJHJ84KF70nfQ8ZMdnQa',
                'grant_type' => 'authorization_code',
                'redirect_uri' => 'https://cityportal.ddev.site/',
                'code' => $code,
            ],
            'verify' => FALSE,
        ]);
    }

    private function getIdamConfig(): string
    {
        return $this->vaultConfigService->getGlobalVariables()['applicationConfig']['config']['idamconfig'];
    }

    public function checkEmailExists(string $email, string $access_token, string $api_url, string $api_version): bool
    {
        try {
            $full_url = $api_url . 'tiotcitizenapp' . $api_version . 'user/details';
            $response = $this->httpClient->request("POST", $full_url, [
                'json' => ['userId' => $email],
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => self::APP_JSON,
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
            $idamconfig = $this->vaultConfigService->getGlobalVariables()['applicationConfig']['config']['idamconfig'];
            $response = $this->httpClient->request('POST', self::SECURE_LINK . $idamconfig . '/oidc/logout', [
                'headers' => [
                    'Content-Type' => self::FORM_URLENCODED,
                    'Cookie' => $cookies,
                ],
                'form_params' => [
                    'response_mode' => 'direct',
                    'id_token_hint' => $id_token_hint,
                ],
                'verify' => FALSE, // 🚨 disables SSL verification
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
     * Perform the full OAuth login flow: get flow ID, authenticate user, exchange code for token.
     */
    public function performOAuthLogin(string $email, string $password): ?array
    {
        // Step 1: Get Flow ID
        $flowId = $this->getFlowId();
        if (!$flowId) {
            throw new \Exception('Flow ID not received from OAuth server.');
        }

        // Step 2: Authenticate user with email & password
        $authResponse = $this->authenticateUser($flowId, $email, $password);
        if (empty($authResponse['success']) || empty($authResponse['code'])) {
            $msg = $authResponse['message'] ?? 'Authorization code not received.';
            throw new \Exception($msg);
        }

        // Step 3: Exchange authorization code for token
        $tokenData = $this->exchangeCodeForToken($authResponse['code']);
        if (empty($tokenData['access_token']) || empty($tokenData['id_token'])) {
            throw new \Exception('Failed to receive access or ID token.');
        }

        return $tokenData;
    }

    public function extractEmailFromJwt(string $idToken): ?string
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            return NULL;
        }
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), TRUE);
        return $payload['sub'] ?? NULL;
    }

    /**
     * Validate email existence via external API.
     */
    public function validateEmail(string $email): bool
    {
        $accessToken = $this->apimanTokenService->getApimanAccessToken();
        $globals = $this->vaultConfigService->getGlobalVariables();

        $apiUrl = $globals['apiManConfig']['config']['apiUrl'] ?? '';
        $apiVersion = $globals['apiManConfig']['config']['apiVersion'] ?? '';

        return $this->checkEmailExists($email, $accessToken, $apiUrl, $apiVersion);
    }
}
