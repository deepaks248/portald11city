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

    private function format_user_agent($userAgent)
    {
        // Detect browser
        switch (TRUE) {
            case stripos($userAgent, 'Edg') !== FALSE:
                $browser = 'Microsoft Edge';
                break;
            case stripos($userAgent, 'Chrome') !== FALSE && stripos($userAgent, 'Chromium') === FALSE:
                $browser = 'Chrome';
                break;
            case stripos($userAgent, 'Firefox') !== FALSE:
                $browser = 'Firefox';
                break;
            case stripos($userAgent, 'Safari') !== FALSE && stripos($userAgent, 'Chrome') === FALSE:
                $browser = 'Safari';
                break;
            case stripos($userAgent, 'Opera') !== FALSE || stripos($userAgent, 'OPR') !== FALSE:
                $browser = 'Opera';
                break;
            default:
                $browser = 'Unknown Browser';
        }

        // Detect device/OS
        switch (TRUE) {
            case stripos($userAgent, 'Windows') !== FALSE:
                $device = 'Desktop (Windows)';
                break;
            case stripos($userAgent, 'Macintosh') !== FALSE || stripos($userAgent, 'Mac OS X') !== FALSE:
                $device = 'Desktop (Mac)';
                break;
            case stripos($userAgent, 'iPhone') !== FALSE:
                $device = 'Mobile (iPhone)';
                break;
            case stripos($userAgent, 'iPad') !== FALSE:
                $device = 'Tablet (iPad)';
                break;
            case stripos($userAgent, 'Android') !== FALSE && stripos($userAgent, 'Mobile') !== FALSE:
                $device = 'Mobile (Android)';
                break;
            case stripos($userAgent, 'Android') !== FALSE:
                $device = 'Tablet (Android)';
                break;
            case stripos($userAgent, 'Linux') !== FALSE:
                $device = 'Linux';
                break;
            default:
                $device = 'Unknown Device/OS';
        }

        if ($browser === $userAgent && $device === $userAgent) {
            return $userAgent;
        }
        return "{$browser}, {$device}";
    }

    public function activeSession()
    {
        $session = \Drupal::service('session');
        $accessToken = $session->get('login_logout.access_token');
        $storedLoginTime = $session->get('login_logout.login_time');

        $apiSessions = $this->get_api_sessions($accessToken);
        $closestSessionId = $this->find_closest_sessionid($apiSessions, $storedLoginTime);

        [$currentUserSessions, $otherUserSessions] =
            $this->categorize_sessions($apiSessions, $closestSessionId, $accessToken);

        return [
            '#title' => $this->t('Active Sessions'),
            '#theme' => 'active_sessions_page',
            '#currentUserSessions' => $currentUserSessions,
            '#otherUserSessions' => $otherUserSessions,
            '#cache' => ['max-age' => 0],
        ];
    }

    /**
     * Fetch sessions from API and return array.
     */
    private function get_api_sessions(string $accessToken): array
    {
        $sessions = $this->sessionService->fetchActiveSessions($accessToken);
        return $sessions['sessions'] ?? [];
    }

    /**
     * Find closest session ID based on login time.
     */
    private function find_closest_sessionid(array $apiSessions, $storedLoginTime): ?string
    {
        if (empty($storedLoginTime) || empty($apiSessions)) {
            return NULL;
        }

        $targetTimeMs = $storedLoginTime * 1000;
        $closestId = NULL;
        $closestDiff = PHP_INT_MAX;

        foreach ($apiSessions as $session) {
            if (empty($session['loginTime'])) {
                continue;
            }

            $diff = abs($session['loginTime'] - $targetTimeMs);

            if ($diff === 0) {
                return $session['id']; // perfect match
            }

            if ($diff < $closestDiff) {
                $closestDiff = $diff;
                $closestId = $session['id'];
            }
        }

        return $closestId;
    }

    /**
     * Categorize sessions into current user and other users.
     */
    private function categorize_sessions(array $apiSessions, ?string $closestId, string $accessToken): array
    {
        $currentUser = [];
        $others = [];

        foreach ($apiSessions as &$session) {
            $this->format_session($session, $accessToken);

            if ($session['id'] === $closestId) {
                $currentUser[] = $session;
            } else {
                $others[] = $session;
            }
        }

        return [$currentUser, $others];
    }

    /**
     * Format/normalize session properties.
     */
    private function format_session(array &$session, string $accessToken): void
    {
        $timestamp = (int) ($session['loginTime'] / 1000);

        $session['accessToken'] = $accessToken;
        $session['userAgentFormatted'] = $this->format_user_agent($session['userAgent'] ?? '');
        $session['loginTimeSeconds'] = $timestamp;
        $session['formattedLoginTime'] = $this->dateFormatter->format(
            $timestamp,
            'custom',
            'd-m-Y, h:i:s',
            'Asia/Kolkata'
        );
    }

    public function endSession(string $session_id)
    {
        $session = \Drupal::service('session');
        $accessToken = $session->get('login_logout.access_token');
        $active_session_id_token = $session->get('login_logout.active_session_id_token');
        $my_account_url = '/my-account';
        if ($accessToken === NULL) {
            $this->messenger()->addError($this->t('Failed to retrieve access token.'));
        }

        [$id] = explode('--', $session_id);

        try {
            $is_my_session = ($active_session_id_token == $id);
            if ($is_my_session) {
                $this->sessionService->terminateSession($active_session_id_token, $accessToken);
                return new RedirectResponse('/logout');
            } else {
                $this->sessionService->terminateSession($id, $accessToken);
                return new RedirectResponse($my_account_url);
            }
        } catch (\Exception $e) {
            $this->messenger()->addError($this->t('An error occurred while ending the session: @message', ['@message' => $e->getMessage()]));
            return new RedirectResponse($my_account_url);
        }
    }

    public function endAllSessions()
    {
        $session = \Drupal::service('session');
        $accessToken = $session->get('login_logout.access_token');
        $my_account_url = '/my-account';
        if ($accessToken === NULL) {
            $this->messenger()->addError($this->t('Failed to retrieve access token.'));
            return new RedirectResponse($my_account_url);
        }

        try {
            $this->sessionService->terminateAllOtherSessions($accessToken);
            return new RedirectResponse('/logout');
        } catch (\Exception $e) {
            $this->messenger()->addError($this->t('An error occurred while ending all sessions: @message', ['@message' => $e->getMessage()]));
            return new RedirectResponse($my_account_url);
        }
    }
}
