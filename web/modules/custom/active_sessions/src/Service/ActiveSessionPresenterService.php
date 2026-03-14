<?php

namespace Drupal\active_sessions\Service;

use Drupal\Core\Datetime\DateFormatterInterface;

class ActiveSessionPresenterService
{
    protected DateFormatterInterface $dateFormatter;

    public function __construct(DateFormatterInterface $dateFormatter)
    {
        $this->dateFormatter = $dateFormatter;
    }

    public function prepareSessions(array $sessions, ?int $loginTime, string $accessToken): array
    {
        $currentSessionId = $this->findClosestSessionId($sessions, $loginTime);
        $current = [];
        $others = [];

        foreach ($sessions as $session) {
            $normalized = $this->normalizeSession($session, $accessToken);

            if (($session['id'] ?? NULL) === $currentSessionId) {
                $current[] = $normalized;
            }
            else {
                $others[] = $normalized;
            }
        }

        return [$current, $others];
    }

    protected function findClosestSessionId(array $sessions, ?int $loginTime): ?string
    {
        if (empty($loginTime) || empty($sessions)) {
            return NULL;
        }

        $targetMs = $loginTime * 1000;
        $closestId = NULL;
        $closestDiff = PHP_INT_MAX;

        foreach ($sessions as $session) {
            if (empty($session['loginTime'])) {
                continue;
            }

            $diff = abs($session['loginTime'] - $targetMs);
            if ($diff < $closestDiff) {
                $closestDiff = $diff;
                $closestId = $session['id'];
            }

            if ($diff === 0) {
                break;
            }
        }

        return $closestId;
    }

    protected function normalizeSession(array $session, string $accessToken): array
    {
        $timestamp = (int) (($session['loginTime'] ?? 0) / 1000);

        $session['accessToken'] = $accessToken;
        $session['userAgentFormatted'] = $this->formatUserAgent($session['userAgent'] ?? '');
        $session['loginTimeSeconds'] = $timestamp;
        $session['formattedLoginTime'] = $this->dateFormatter->format(
            $timestamp,
            'custom',
            'd-m-Y, h:i:s',
            'Asia/Kolkata'
        );

        return $session;
    }

    protected function formatUserAgent(string $userAgent): string
    {
        $browser = $this->detectValue($userAgent, $this->browserMap(), 'Unknown Browser');
        $device = $this->detectValue($userAgent, $this->deviceMap(), 'Unknown Device/OS');

        return ($browser === 'Unknown Browser' && $device === 'Unknown Device/OS')
            ? $userAgent
            : "{$browser}, {$device}";
    }

    protected function detectValue(string $userAgent, array $map, string $default): string
    {
        foreach ($map as $label => $patterns) {
            foreach ((array) $patterns as $pattern) {
                if (stripos($userAgent, $pattern) !== FALSE) {
                    return $label;
                }
            }
        }

        return $default;
    }

    protected function browserMap(): array
    {
        return [
            'Microsoft Edge' => ['Edg'],
            'Chrome' => ['Chrome'],
            'Firefox' => ['Firefox'],
            'Safari' => ['Safari'],
            'Opera' => ['Opera', 'OPR'],
        ];
    }

    protected function deviceMap(): array
    {
        return [
            'Mobile (iPhone)' => ['iPhone'],
            'Tablet (iPad)' => ['iPad'],
            'Desktop (Windows)' => ['Windows'],
            'Desktop (Mac)' => ['Macintosh', 'Mac OS X'],
            'Mobile (Android)' => ['Android Mobile'],
            'Tablet (Android)' => ['Android'],
            'Linux' => ['Linux'],
        ];
    }
}
