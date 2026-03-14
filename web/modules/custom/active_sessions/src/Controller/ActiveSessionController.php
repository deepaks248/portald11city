<?php

namespace Drupal\active_sessions\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\login_logout\Service\OAuthLoginService;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\active_sessions\Service\ActiveSessionService;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ActiveSessionController extends ControllerBase
{

    protected $oauthLoginService;
    protected $requestStack;
    protected $sessionService;
    protected $dateFormatter;
    public const MY_ACCOUNT_PATH = '/my-account';

    public function __construct(
        OAuthLoginService $oauthLoginService,
        RequestStack $requestStack,
        ActiveSessionService $sessionService,
        DateFormatterInterface $dateFormatter
    ) {
        $this->oauthLoginService = $oauthLoginService;
        $this->requestStack = $requestStack;
        $this->sessionService = $sessionService;
        $this->dateFormatter = $dateFormatter;
    }

    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('login_logout.oauth_login_service'),
            $container->get('request_stack'),
            $container->get('active_sessions.session_service'),
            $container->get('date.formatter')
        );
    }

    private function format_user_agent(string $userAgent): string
    {
        $browser = $this->detectValue($userAgent, $this->browserMap(), 'Unknown Browser');
        $device  = $this->detectValue($userAgent, $this->deviceMap(), 'Unknown Device/OS');

        return ($browser === 'Unknown Browser' && $device === 'Unknown Device/OS')
            ? $userAgent
            : "{$browser}, {$device}";
    }

    private function detectValue(string $ua, array $map, string $default): string
    {
        foreach ($map as $label => $patterns) {
            foreach ((array) $patterns as $pattern) {
                if (stripos($ua, $pattern) !== FALSE) {
                    return $label;
                }
            }
        }

        return $default;
    }

    private function browserMap(): array
    {
        return [
            'Microsoft Edge' => ['Edg'],
            'Chrome'         => ['Chrome'],
            'Firefox'        => ['Firefox'],
            'Safari'         => ['Safari'],
            'Opera'          => ['Opera', 'OPR'],
        ];
    }


    private function deviceMap(): array
    {
        return [
            'Mobile (iPhone)'   => ['iPhone'],
            'Tablet (iPad)'     => ['iPad'],
            'Desktop (Windows)' => ['Windows'],
            'Desktop (Mac)'     => ['Macintosh', 'Mac OS X'],
            'Mobile (Android)'  => ['Android Mobile'],
            'Tablet (Android)'  => ['Android'],
            'Linux'             => ['Linux'],
        ];
    }


    public function activeSession(): array
    {
        $session     = \Drupal::service('session');
        $accessToken = $session->get('login_logout.access_token');
        $loginTime   = $session->get('login_logout.login_time');

        $apiSessions = $this->getApiSessions($accessToken);
        $closestId   = $this->findClosestSessionId($apiSessions, $loginTime);

        [$current, $others] = $this->prepareSessions(
            $apiSessions,
            $closestId,
            $accessToken
        );

        return $this->buildResponse($current, $others);
    }


    private function getApiSessions(?string $accessToken): array
    {
        $sessions = $this->sessionService->fetchActiveSessions($accessToken);
        return $sessions['sessions'] ?? [];
    }

    private function findClosestSessionId(array $sessions, ?int $loginTime): ?string
    {
        if (empty($loginTime) || empty($sessions)) {
            return NULL;
        }

        $targetMs   = $loginTime * 1000;
        $closestId  = NULL;
        $closestDiff = PHP_INT_MAX;

        foreach ($sessions as $session) {
            if (empty($session['loginTime'])) {
                continue;
            }

            $diff = abs($session['loginTime'] - $targetMs);

            if ($diff < $closestDiff) {
                $closestDiff = $diff;
                $closestId   = $session['id'];
            }

            if ($diff === 0) {
                break;
            }
        }

        return $closestId;
    }

    private function prepareSessions(
        array $sessions,
        ?string $currentSessionId,
        string $accessToken
    ): array {
        $current = [];
        $others  = [];

        foreach ($sessions as $session) {
            $normalized = $this->normalizeSession(
                $session,
                $accessToken
            );

            if ($session['id'] === $currentSessionId) {
                $current[] = $normalized;
            } else {
                $others[] = $normalized;
            }
        }

        return [$current, $others];
    }

    private function normalizeSession(array $session, string $accessToken): array
    {
        $timestamp = (int) (($session['loginTime'] ?? 0) / 1000);

        $session['accessToken']          = $accessToken;
        $session['userAgentFormatted']   = $this->format_user_agent($session['userAgent'] ?? '');
        $session['loginTimeSeconds']     = $timestamp;
        $session['formattedLoginTime']   = $this->dateFormatter->format(
            $timestamp,
            'custom',
            'd-m-Y, h:i:s',
            'Asia/Kolkata'
        );

        return $session;
    }

    private function buildResponse(array $current, array $others): array
    {
        return [
            '#title' => $this->t('Active Sessions'),
            '#theme' => 'active_sessions_page',
            '#currentUserSessions' => $current,
            '#otherUserSessions'  => $others,
            '#cache' => ['max-age' => 0],
        ];
    }

    public function endSession(string $session_id)
    {
        $session = \Drupal::service('session');
        $accessToken = $session->get('login_logout.access_token');
        $active_session_id_token = $session->get('login_logout.active_session_id_token');
        $response = new RedirectResponse(self::MY_ACCOUNT_PATH);

        if ($accessToken === NULL) {
            $this->messenger()->addError($this->t('Failed to retrieve access token.'));
            return $response;
        }

        [$id, $access_token] = explode('--', $session_id) + [NULL, NULL];
        unset($access_token);

        try {
            $is_my_session = ($active_session_id_token == $id);

            if ($is_my_session) {
                $this->sessionService->terminateSession($active_session_id_token, $accessToken);
                $response = new RedirectResponse('/logout');
            } else {
                $this->sessionService->terminateSession($id, $accessToken);
                $response = new RedirectResponse(self::MY_ACCOUNT_PATH);
            }
        } catch (\Exception $e) {
            $this->messenger()->addError(
                $this->t(
                    'An error occurred while ending the session: @message',
                    ['@message' => $e->getMessage()]
                )
            );
        }

        return $response;
    }

    public function endAllSessions()
    {
        $session = \Drupal::service('session');
        $accessToken = $session->get('login_logout.access_token');

        if ($accessToken === NULL) {
            $this->messenger()->addError($this->t('Failed to retrieve access token.'));
            return new RedirectResponse(self::MY_ACCOUNT_PATH);
        }

        try {
            $this->sessionService->terminateAllOtherSessions($accessToken);
            return new RedirectResponse('/logout');
        } catch (\Exception $e) {
            $this->messenger()->addError($this->t('An error occurred while ending all sessions: @message', ['@message' => $e->getMessage()]));
            return new RedirectResponse(self::MY_ACCOUNT_PATH);
        }
    }
}
