<?php

declare(strict_types=1);

namespace Drupal\secaudit\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Safer AuditService for XSS detection with reduced false positives.
 *
 * Scans only: query params, POST/form body, cookies, and JSON body (when
 * Content-Type contains "application/json"). Skips headers and path scanning
 * to avoid matching Drupal internal payloads and admin pages.
 */
class AuditService
{
    protected RequestStack $requestStack;
    protected LoggerChannelFactoryInterface $loggerFactory;

    /**
     * Patterns to detect XSS-ish content.
     *
     * @var string[]
     */
    protected array $xssPatterns = [
        '/<script\b[^>]*>(.*?)<\/script>/is',
        '/on\w+\s*=/i',
        '/javascript\s*:/i',
        '/\b(alert|eval|confirm|prompt)\s*\(/i',
        '/document\.cookie/i',
        '/<img\b[^>]*on\w+\s*=/i',
        '/<iframe\b/i',
        '/<svg\b[^>]*>/i',
        '/srcdoc\s*=/i',
        '/data\s*:\s*text\/html/i',
        '/data\s*:\s*text\/javascript/i',
        '/"\\s*<\\w|\'\\s*<\\w/',
    ];

    /** Max string length to inspect. */
    protected int $maxScanLength = 4096;

    /** Max findings to record per request. */
    protected int $maxFindings = 10;

    /**
     * Path prefixes to ignore (admin, assets, internal endpoints).
     *
     * Add or remove prefixes as appropriate for your site.
     *
     * @var string[]
     */
    protected array $ignorePathPrefixes = [
        '/admin',
        '/core',
        '/profiles',
        '/modules',
        '/sites/default/files',
        '/sites/simpletest',
        '/favicon.ico',
        '/robots.txt',
        '/_profiler',          // Symfony profiler
        '/visitors/_track',    // Matomo / analytics endpoints
    ];

    public function __construct(RequestStack $request_stack, LoggerChannelFactoryInterface $logger_factory)
    {
        $this->requestStack = $request_stack;
        $this->loggerFactory = $logger_factory;
    }

    /**
     * Detect ACE3 – Force Browsing Attempts.
     *
     * Logs when an unauthenticated or unauthorized user accesses
     * sensitive paths directly via URL manipulation.
     */
    public function detectForceBrowsing(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $path = $request->getPathInfo();

        // Paths that should never be accessed directly without permissions
        $restrictedPatterns = [
            '#^/admin#',
            '#^/user/\d+/edit#',
            '#^/node/\d+/edit#',
            '#^/node/\d+/delete#',
            '#^/admin/config#',
        ];

        foreach ($restrictedPatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                // If user is anonymous OR lacks admin access
                if (!$request->getSession()->has('uid') || !\Drupal::currentUser()->hasPermission('access administration pages')) {
                    $this->loggerFactory
                        ->get('secaudit')
                        ->warning(
                            'ACE3 Force Browsing attempt detected.',
                            [
                                'ip' => $request->headers->all()['x-real-ip'][0],
                                'path' => $path,
                                'method' => $request->getMethod(),
                                'user_id' => \Drupal::currentUser()->id(),
                            ]
                        );
                }
                return;
            }
        }
    }

    /**
     * Detects RE1 – Unexpected HTTP Commands.
     *
     * Logs if request method is not explicitly allowed (GET, POST).
     */
    public function detectUnexpectedHttpMethod(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $method = strtoupper($request->getMethod());

        // Allow only GET and POST
        $allowedMethods = ['GET', 'POST'];

        if (!in_array($method, $allowedMethods, TRUE)) {
            $logger = $this->loggerFactory->get('secaudit');

            $logger->warning(
                'RE1: Unexpected HTTP method detected.',
                [
                    'method' => $method,
                    'path' => $request->getPathInfo(),
                    'ip' => $request->headers->all()['x-real-ip'][0],
                    'user_agent' => $request->headers->get('User-Agent'),
                ]
            );
        }
    }

    /**
     * Detect RE2: Attempts to invoke unsupported HTTP methods.
     *
     * Allowed methods: GET, POST
     */
    public function detectUnsupportedHttpMethods(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $allowedMethods = ['GET', 'POST'];
        $method = strtoupper($request->getMethod());

        if (!in_array($method, $allowedMethods, TRUE)) {
            $logger = $this->loggerFactory->get('secaudit');

            $logger->warning('RE2: Unsupported HTTP method attempt detected.', [
                'method' => $method,
                'path' => $request->getPathInfo(),
                'ip' => $request->headers->all()['x-real-ip'][0],
                'user_agent' => $request->headers->get('User-Agent'),
            ]);
        }
    }

    /**
     * Detect SE1–SE6 – Session & cookie tampering attempts.
     */
    public function detectCookieTampering(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        $logger = $this->loggerFactory->get('secaudit');

        $currentCookies = $request->cookies->all();
        $currentIp = $request->headers->all()['x-real-ip'][0];
        $currentUa = (string) $request->headers->get('User-Agent');
        $currentSessionId = $session->getId();

        // Load previous snapshot
        $snapshot = $session->get('secaudit.session_snapshot');

        // First request → establish baseline
        if ($snapshot === null) {
            $session->set('secaudit.session_snapshot', [
                'cookies' => $currentCookies,
                'ip' => $currentIp,
                'ua' => $currentUa,
                'session_id' => $currentSessionId,
            ]);
            return;
        }

        $previousCookies = $snapshot['cookies'] ?? [];

        /** ---------------- SE2: New cookies added ---------------- */
        $added = array_diff_key($currentCookies, $previousCookies);
        if (!empty($added)) {
            $logger->warning('SE2: New cookies added during session for IP: @ip and Path: @path Cookies added: @cookies_added', [
                '@ip' => $currentIp,
                '@path' => $request->getPathInfo(),
                '@cookies_added' => implode(', ', array_keys($added)),
                '@uid' => \Drupal::currentUser()->id(),
            ]);
        }

        /** ---------------- SE3: Cookies deleted ---------------- */
        $deleted = array_diff_key($previousCookies, $currentCookies);
        if (!empty($deleted)) {
            $logger->warning('SE3: Existing cookies deleted during session. IP Address: @ip, Path: @path, Cookies Deleted: @cookies_deleted', [
                '@ip' => $currentIp,
                '@path' => $request->getPathInfo(),
                '@cookies_deleted' => implode(', ', array_keys($deleted)),
                '@uid' => \Drupal::currentUser()->id(),
            ]);
        }

        /** ---------------- SE1: Cookie value modified ---------------- */
        foreach ($currentCookies as $name => $value) {
            if (
                isset($previousCookies[$name]) &&
                hash('sha256', (string) $previousCookies[$name]) !== hash('sha256', (string) $value)
            ) {
                $logger->warning('SE1: Cookie value modified. IP Address: @ip, Path: @path, Cookie Name: @cookie_name', [
                    '@ip' => $currentIp,
                    '@path' => $request->getPathInfo(),
                    '@cookie_name' => $name,
                    '@uid' => \Drupal::currentUser()->id(),
                ]);
            }
        }

        /** ---------------- SE4: Session ID substitution ---------------- */
        if (!empty($snapshot['session_id']) && $snapshot['session_id'] !== $currentSessionId) {
            $logger->warning(
                'SE4: Session ID changed mid-session. IP: @ip | UID: @uid | Old SID: @old_sid | New SID: @new_sid',
                [
                    '@ip' => $currentIp,
                    '@uid' => \Drupal::currentUser()->id(),
                    '@old_sid' => $snapshot['session_id'],
                    '@new_sid' => $currentSessionId,
                ]
            );
        }

        /** ---------------- SE5: Source IP changed ---------------- */
        if (!empty($snapshot['ip']) && $snapshot['ip'] !== $currentIp) {
            $logger->warning('SE5: Source IP changed during active session. Old IP: @old_ip and New IP: @new_ip for User: @uid', [
                '@old_ip' => $snapshot['ip'],
                '@new_ip' => $currentIp,
                '@uid' => \Drupal::currentUser()->id(),
            ]);
        }

        /** ---------------- SE6: User-Agent changed ---------------- */
        if (!empty($snapshot['ua']) && $snapshot['ua'] !== $currentUa) {
            $logger->warning('SE6: User-Agent changed mid-session. Old User Agent: @old_ua, New User Agent: @new_ua', [
                '@old_ua' => $snapshot['ua'],
                '@new_ua' => $currentUa,
                '@uid' => \Drupal::currentUser()->id(),
            ]);
        }

        // Update snapshot for next request
        $session->set('secaudit.session_snapshot', [
            'cookies' => $currentCookies,
            'ip' => $currentIp,
            'ua' => $currentUa,
            'session_id' => $currentSessionId,
        ]);
    }

    public function detectIE1(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request->attributes->get('_secaudit_ee1_detected')) {
            return [];
        }

        if (!$request) {
            return [];
        }

        $pathInfo = $request->getPathInfo() ?? '/';
        foreach ($this->ignorePathPrefixes as $prefix) {
            if (str_starts_with($pathInfo, $prefix)) {
                return [];
            }
        }

        $findings = [];

        $inputs = [
            'query' => $request->query->all(),
            'request' => $request->request->all(),
            'cookies' => $request->cookies->all(),
        ];

        $contentType = (string) $request->headers->get('Content-Type', '');
        if (stripos($contentType, 'application/json') !== FALSE) {
            $decoded = json_decode((string) $request->getContent(), TRUE);
            if (is_array($decoded)) {
                $inputs['json_body'] = $decoded;
            }
        }

        foreach ($inputs as $type => $values) {
            $this->scanIE1Recursive($type, $values, $findings);
            if (count($findings) >= $this->maxFindings) {
                break;
            }
        }

        if (!empty($findings)) {
            $this->logIE1($request, $findings);
        }

        return $findings;
    }

    protected function scanIE1Recursive(string $type, $values, array &$findings): void
    {
        if (count($findings) >= $this->maxFindings) {
            return;
        }

        if (is_array($values)) {
            foreach ($values as $v) {
                $this->scanIE1Recursive($type, $v, $findings);
            }
            return;
        }

        if (!is_scalar($values)) {
            return;
        }

        $value = (string) $values;
        if (strlen($value) > $this->maxScanLength) {
            return;
        }

        // Only raw + single decode and double decode
        $variants = [
            $value,
            rawurldecode($value),
            html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            rawurldecode(rawurldecode($value)),  // Double URL decode
            html_entity_decode(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8')  // Double HTML decode
        ];

        foreach ($variants as $variant) {
            foreach ($this->xssPatterns as $pattern) {
                if (preg_match($pattern, $variant)) {
                    $findings[] = [
                        'type' => $type,
                        'value' => $value,
                        'pattern' => $pattern,
                    ];
                    return;
                }
            }
        }
    }

    protected function logIE1($request, array $findings): void
    {
        $this->loggerFactory->get('secaudit')->warning(
            'IE1: Cross Site Scripting Attempt detected. IP: @ip, Path: @path, Findings Count: @count',
            [
                '@ip' => $request->headers->all()['x-real-ip'][0],
                '@path' => $request->getPathInfo(),
                '@count' => count($findings),
                '@details' => $findings,
            ]
        );
    }

    public function detectEE1(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $inputs = array_merge(
            $request->query->all(),
            $request->request->all(),
            $request->cookies->all()
        );

        foreach ($inputs as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $value = (string) $value;

            // URL decoded twice
            $once = rawurldecode($value);
            $twice = rawurldecode($once);

            // Check if there's a mismatch after double-decoding (even if the value appears unchanged in the logs)
            if ($twice !== $once || $this->containsHTMLEntity($twice)) {
                $this->logEE1($request, $value, 'double_url_encoding');
                $request->attributes->set('_secaudit_ee1_detected', TRUE);
                return;
            }

            // HTML entity recursion checks (for patterns like &lt;, &gt;, etc.)
            $once = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $twice = html_entity_decode($once, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($twice !== $once) {
                $this->logEE1($request, $value, 'double_html_encoding');
                $request->attributes->set('_secaudit_ee1_detected', TRUE);
                return;
            }

            // Other encoding checks can be added here as needed
        }
    }

    /**
     * Check if the string contains HTML entities like &lt;, &gt;, etc.
     */
    protected function containsHTMLEntity(string $value): bool
    {
        return preg_match('/&(lt|gt|amp|quot|apos|#\d+);/', $value) === 1;
    }

    protected function looksLikeBase64(string $s): bool
    {
        $len = strlen($s);
        if ($len < 8 || $len % 4 !== 0) {
            return FALSE;
        }
        return (bool) preg_match('/^[A-Za-z0-9+\/]+=*$/', $s);
    }

    protected function logEE1($request, string $value, string $reason): void
    {
        $this->loggerFactory->get('secaudit')->warning(
            'EE1: Double Encoded Characters detected. IP: @ip, Path: @path, Reason: @reason, Sample Value: @sample',
            [
                '@ip' => $request->headers->all()['x-real-ip'][0],
                '@path' => $request->getPathInfo(),
                '@reason' => $reason,
                '@sample' => substr($value, 0, 200),  // Log the first 200 chars of the value
            ]
        );
    }

    /**
     * Detect EE2 – Unexpected Encoding Used.
     *
     * Flags unusual encoding techniques not normally produced
     * by browsers or standard HTML forms.
     */
    public function detectEE2(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        // Avoid duplicate EE logs per request
        if ($request->attributes->get('_secaudit_ee2_detected')) {
            return;
        }

        $inputs = array_merge(
            $request->query->all(),
            $request->request->all(),
            $request->cookies->all()
        );

        foreach ($inputs as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $value = (string) $value;

            /** ---------------- Hex encoding (\x3c) ---------------- */
            if (preg_match('/\\\\x[0-9a-fA-F]{2}/', $value)) {
                $this->logEE2($request, $value, 'hex_encoding');
                return;
            }

            /** ---------------- Unicode escape encoding (\u003c) ---------------- */
            if (preg_match('/\\\\u[0-9a-fA-F]{4}/', $value)) {
                $this->logEE2($request, $value, 'unicode_escape_encoding');
                return;
            }

            /** ---------------- Octal encoding (\074) ---------------- */
            if (preg_match('/\\\\[0-7]{2,3}/', $value)) {
                $this->logEE2($request, $value, 'octal_encoding');
                return;
            }

            /** ---------------- Mixed encoding styles ---------------- */
            if (
                preg_match('/%[0-9a-fA-F]{2}/', $value) &&
                (preg_match('/\\\\x[0-9a-fA-F]{2}/', $value) || preg_match('/\\\\u[0-9a-fA-F]{4}/', $value))
            ) {
                $this->logEE2($request, $value, 'mixed_encoding_styles');
                return;
            }

            /** ---------------- Binary / control characters ---------------- */
            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
                $this->logEE2($request, $value, 'binary_or_control_characters');
                return;
            }

            /** ---------------- UTF-16 / UTF-32 null-byte patterns ---------------- */
            if (
                preg_match('/\x00.{1}\x00/', $value) ||
                preg_match('/.{1}\x00.{1}\x00/', $value)
            ) {
                $this->logEE2($request, $value, 'multi_byte_null_padding');
                return;
            }
        }
    }

    /**
     * Log EE2 detection.
     */
    protected function logEE2($request, string $value, string $reason): void
    {
        $this->loggerFactory->get('secaudit')->warning(
            'EE2: Unexpected encoding used. IP: @ip, Path: @path, Reason: @reason, Sample: @sample',
            [
                '@ip' => $request->headers->all()['x-real-ip'][0],
                '@path' => $request->getPathInfo(),
                '@reason' => $reason,
                '@sample' => substr($value, 0, 200),
            ]
        );

        $request->attributes->set('_secaudit_ee2_detected', TRUE);
    }
}
