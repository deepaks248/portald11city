<?php

namespace Drupal\login_logout\Service;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\global_module\Service\GlobalVariablesService;
use Drupal\Core\Site\Settings;

class OAuthLoginService
{
    public const SECURE_LINK = 'https://';
    public const APP_JSON = 'application/json';
    public const FORM_URLENCODED = 'application/x-www-form-urlencoded';
    protected $httpClient;
    protected $logger;
    protected $requestStack;
    protected $globalVariablesService;

    public function __construct(
        ClientInterface $http_client,
        LoggerInterface $logger,
        RequestStack $requestStack,
        GlobalVariablesService $globalVariablesService
    ) {
        $this->globalVariablesService = $globalVariablesService;
        $this->httpClient = $http_client;
        $this->logger = $logger;
        $this->requestStack = $requestStack;
    }

    public function getFlowId(): ?string
    {
        try {
            $idamconfig = $this->globalVariablesService->getGlobalVariables()['applicationConfig']['config']['idamconfig'];
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

    public function format_user_agent($userAgent)
    {
        $browser = $userAgent;
        $device  = $userAgent;

        // Detect browser
        switch (TRUE) {
            case stripos($browser, 'Edg') !== FALSE:
                $browser = 'Microsoft Edge';
                break;
            case stripos($browser, 'Chrome') !== FALSE && stripos($browser, 'Chromium') === FALSE:
                $browser = 'Chrome';
                break;
            case stripos($browser, 'Firefox') !== FALSE:
                $browser = 'Firefox';
                break;
            case stripos($browser, 'Safari') !== FALSE && stripos($browser, 'Chrome') === FALSE:
                $browser = 'Safari';
                break;
            case stripos($browser, 'Opera') !== FALSE || stripos($browser, 'OPR') !== FALSE:
                $browser = 'Opera';
                break;
            default:
                $browser = 'Unknown Browser';
                break;
        }

        // Detect device/OS
        switch (TRUE) {
            case stripos($device, 'Windows') !== FALSE:
                $device = 'Desktop (Windows)';
                break;
            case stripos($device, 'Macintosh') !== FALSE || stripos($device, 'Mac OS X') !== FALSE:
                $device = 'Desktop (Mac)';
                break;
            case stripos($device, 'iPhone') !== FALSE:
                $device = 'Mobile (iPhone)';
                break;
            case stripos($device, 'iPad') !== FALSE:
                $device = 'Tablet (iPad)';
                break;
            case stripos($device, 'Android') !== FALSE && stripos($device, 'Mobile') !== FALSE:
                $device = 'Mobile (Android)';
                break;
            case stripos($device, 'Android') !== FALSE:
                $device = 'Tablet (Android)';
                break;
            case stripos($device, 'Linux') !== FALSE:
                $device = 'Linux';
                break;
            default:
                $device = 'Unknown Device/OS';
                break;
        }

        if ($browser === $userAgent && $device === $userAgent) {
            return $userAgent;
        }
        return "{$browser}, {$device}";
    }

    public function authenticateUser(string $flow_id, string $email, string $password): ?array
    {
        $userAgent = $this->requestStack->getCurrentRequest()->headers->get('User-Agent');

        try {
            $payload = [
                "flowId" => $flow_id,
                "selectedAuthenticator" => [
                    "authenticatorId" => Settings::get('idam_local_authenticator_id'),
                    "params" => [
                        "username" => $email,
                        "password" => $password,
                    ],
                ],
            ];
            $idamconfig = $this->globalVariablesService->getGlobalVariables()['applicationConfig']['config']['idamconfig'];
            $response = $this->httpClient->request('POST', self::SECURE_LINK . $idamconfig . '/oauth2/authn', [
                'headers' => [
                    'Accept' => self::APP_JSON,
                    'Content-Type' => self::APP_JSON,
                    'User-Agent' => $userAgent,
                ],
                'json' => $payload,
                'verify' => FALSE,
            ]);

            $result = json_decode($response->getBody()->getContents(), TRUE);
            $this->logger->notice('Authn response: <pre>@data</pre>', ['@data' => print_r($result, TRUE)]);
            $this->logger->notice('User Login Detected. User-Agent: @ua', ['@ua' => $this->format_user_agent($userAgent)]);
            // Success: return code
            if (!empty($result['authData']['code'])) {
                return [
                    'success' => TRUE,
                    'code' => $result['authData']['code'],
                    'message' => NULL,
                ];
            }

            // Active session limit reached
            if (
                !empty($result['nextStep']['authenticators'][0]['authenticator']) &&
                $result['nextStep']['authenticators'][0]['authenticator'] === 'Active Sessions Limit'
            ) {
                $maxSessions = $result['nextStep']['authenticators'][0]['metadata']['additionalData']['MaxSessionCount'] ?? 'unknown';
                $sessions = $result['nextStep']['authenticators'][0]['metadata']['additionalData']['sessions'] ?? '[]';
                $sessions = json_decode($sessions, TRUE);

                $sessionList = '';
                foreach ($sessions as $s) {
                    $sessionList .= "- Browser: {$s['browser']}, Device: {$s['device']}, Last Active: " .
                        date('Y-m-d H:i:s', $s['lastAccessTime'] / 1000) . "\n";
                }

                $this->logger->notice('Active sessions for user @email: @sessions', [
                    '@email' => $email,
                    '@sessions' => $sessionList,
                ]);

                $message = "You have reached the maximum active sessions ($maxSessions).";

                return [
                    'success' => FALSE,
                    'code' => NULL,
                    'message' => $message,
                ];
            }

            // Generic error message from nextStep messages
            if (!empty($result['nextStep']['messages'][0]['message'])) {
                return [
                    'success' => FALSE,
                    'code' => NULL,
                    'message' => $result['nextStep']['messages'][0]['message'],
                ];
            }

            // Generic failure
            return [
                'success' => FALSE,
                'code' => NULL,
                'message' => 'Authentication failed. Please try again.',
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error authenticating user: @msg', ['@msg' => $e->getMessage()]);
            return [
                'success' => FALSE,
                'code' => NULL,
                'message' => 'An error occurred during authentication. Please try again later.',
            ];
        }
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
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return NULL; // Invalid token format
        }

        $payload = $parts[1];

        // Decode Base64Url (replace -_ with +/ and pad with =)
        $payload = strtr($payload, '-_', '+/');
        $mod4 = strlen($payload) % 4;
        if ($mod4) {
            $payload .= str_repeat('=', 4 - $mod4);
        }

        $decoded = json_decode(base64_decode($payload), TRUE);
        return is_array($decoded) ? $decoded : NULL;
    }

    public function exchangeCodeForToken(string $code): ?array
    {
        try {
            $idamconfig = $this->globalVariablesService->getGlobalVariables()['applicationConfig']['config']['idamconfig'];
            $response = $this->httpClient->request('POST', self::SECURE_LINK . $idamconfig . '/oauth2/token', [
                'headers' => [
                    'Content-Type' => self::FORM_URLENCODED,
                ],
                'form_params' => [
                    'client_id' => 'hVBu5NSpBJHJ84KF70nfQ8ZMdnQa',
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => 'https://cityportal.ddev.site/',
                    'code' => $code,
                ],
                'verify' => FALSE,
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
            $idamconfig = $this->globalVariablesService->getGlobalVariables()['applicationConfig']['config']['idamconfig'];
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
        $accessToken = $this->globalVariablesService->getApimanAccessToken();
        $globals = $this->globalVariablesService->getGlobalVariables();

        $apiUrl = $globals['apiManConfig']['config']['apiUrl'] ?? '';
        $apiVersion = $globals['apiManConfig']['config']['apiVersion'] ?? '';

        return $this->checkEmailExists($email, $accessToken, $apiUrl, $apiVersion);
    }
}
