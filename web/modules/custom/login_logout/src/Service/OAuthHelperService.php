<?php

namespace Drupal\login_logout\Service;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Site\Settings;
use Drupal\global_module\Service\VaultConfigService;

class OAuthHelperService
{

    public const SECURE_LINK = 'https://';
    public const APP_JSON = 'application/json';
    protected $logger;
    protected $httpClient;
    protected $vaultConfigService;

    public function __construct(
        ClientInterface $http_client,
        LoggerInterface $logger,
        VaultConfigService $vaultConfigService
    ) {
        $this->httpClient = $http_client;
        $this->logger = $logger;
        $this->vaultConfigService = $vaultConfigService;
    }

    public function detectFromRules(string $agent, array $rules, string $default): string
    {
        foreach ($rules as $label => $conditions) {
            if ($this->matchesConditions($agent, (array) $conditions)) {
                return $label;
            }
        }

        return $default;
    }

    public function matchesConditions(string $agent, array $conditions): bool
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

    public function prepareAuthPayload($flow_id, $email, $password)
    {
        $authenticatorId = $this->vaultConfigService->getGlobalVariables()['applicationConfig']['config']['authenticatorId'];
        return [
            "flowId" => $flow_id,
            "selectedAuthenticator" => [
                "authenticatorId" => $authenticatorId,
                "params" => ["username" => $email, "password" => $password],
            ],
        ];
    }

    public function sendAuthenticationRequest($idamconfig, $payload, $userAgent)
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

    public function parseResponse($response)
    {
        return json_decode($response->getBody()->getContents(), TRUE);
    }

    public function isAuthSuccess($result)
    {
        return !empty($result['authData']['code']);
    }

    public function handleAuthSuccess($result)
    {
        return ['success' => TRUE, 'code' => $result['authData']['code'], 'message' => NULL];
    }

    public function isActiveSessionLimitReached($result)
    {
        return !empty($result['nextStep']['authenticators'][0]['authenticator']) &&
            $result['nextStep']['authenticators'][0]['authenticator'] === 'Active Sessions Limit';
    }

    public function handleSessionLimit($result, $email)
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

    public function formatSessions($sessions)
    {
        $sessionList = '';
        foreach ($sessions as $s) {
            $sessionList .= "- Browser: {$s['browser']}, Device: {$s['device']}, Last Active: " .
                date('Y-m-d H:i:s', $s['lastAccessTime'] / 1000) . "\n";
        }
        return $sessionList;
    }

    public function handleErrorResponse($result)
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

    public function logError($message)
    {
        $this->logger->error('Error authenticating user: @msg', ['@msg' => $message]);
    }

    public function generateErrorResponse()
    {
        return [
            'success' => FALSE,
            'code' => NULL,
            'message' => 'An error occurred during authentication. Please try again later.',
        ];
    }

    public function isValidJwtFormat($jwt)
    {
        return count(explode('.', $jwt)) === 3;
    }

    public function extractPayloadFromJwt($jwt)
    {
        return explode('.', $jwt)[1];
    }

    public function decodeBase64Url($payload)
    {
        // Decode Base64Url (replace -_ with +/ and pad with =)
        $payload = strtr($payload, '-_', '+/');
        $mod4 = strlen($payload) % 4;
        if ($mod4) {
            $payload .= str_repeat('=', 4 - $mod4);
        }

        return json_decode(base64_decode($payload), TRUE);
    }
}
