<?php

declare(strict_types=1);

namespace Drupal\secaudit\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Audits request input for XSS-like payloads.
 */
class InputXssAuditService
{
  protected RequestStack $requestStack;
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
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

  protected int $maxScanLength = 4096;
  protected int $maxFindings = 10;

  /**
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
    '/_profiler',
    '/visitors/_track',
  ];

  public function __construct(RequestStack $request_stack, LoggerChannelFactoryInterface $logger_factory)
  {
    $this->requestStack = $request_stack;
    $this->loggerFactory = $logger_factory;
  }

  public function detectIE1(): array
  {
    $request = $this->requestStack->getCurrentRequest();
    if (!$this->shouldScanRequest($request)) {
      return [];
    }

    $inputs = $this->collectInputs($request);
    $findings = $this->scanInputs($inputs);

    if (!empty($findings)) {
      $this->logIE1($request, $findings);
    }

    return $findings;
  }

  private function shouldScanRequest(?Request $request): bool
  {
    if (!$request || $request->attributes->get('_secaudit_ee1_detected')) {
      return FALSE;
    }

    $pathInfo = $request->getPathInfo() ?? '/';
    foreach ($this->ignorePathPrefixes as $prefix) {
      if (str_starts_with($pathInfo, $prefix)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  private function collectInputs(Request $request): array
  {
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

    return $inputs;
  }

  private function scanInputs(array $inputs): array
  {
    $findings = [];

    foreach ($inputs as $type => $values) {
      $this->scanIE1Recursive($type, $values, $findings);
      if (count($findings) >= $this->maxFindings) {
        break;
      }
    }

    return $findings;
  }

  protected function scanIE1Recursive(string $type, $values, array &$findings): void
  {
    if (!$this->canScanValue($values, $findings)) {
      return;
    }

    if (is_array($values)) {
      $this->scanArrayValues($type, $values, $findings);
      return;
    }

    $value = (string) $values;
    if ($this->isValueTooLong($value)) {
      return;
    }

    $this->scanValueVariants($type, $value, $findings);
  }

  private function canScanValue($values, array $findings): bool
  {
    if (count($findings) >= $this->maxFindings) {
      return FALSE;
    }

    return is_array($values) || is_scalar($values);
  }

  private function scanArrayValues(string $type, array $values, array &$findings): void
  {
    foreach ($values as $value) {
      $this->scanIE1Recursive($type, $value, $findings);
      if (count($findings) >= $this->maxFindings) {
        break;
      }
    }
  }

  private function isValueTooLong(string $value): bool
  {
    return strlen($value) > $this->maxScanLength;
  }

  private function scanValueVariants(string $type, string $value, array &$findings): void
  {
    foreach ($this->generateVariants($value) as $variant) {
      foreach ($this->xssPatterns as $pattern) {
        if (!preg_match($pattern, $variant)) {
          continue;
        }

        $findings[] = [
          'type' => $type,
          'value' => $value,
          'pattern' => $pattern,
        ];
        return;
      }
    }
  }

  private function generateVariants(string $value): array
  {
    return [
      $value,
      rawurldecode($value),
      html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
      rawurldecode(rawurldecode($value)),
      html_entity_decode(
        html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
      ),
    ];
  }

  protected function logIE1($request, array $findings): void
  {
    $this->loggerFactory->get('secaudit')->warning(
      'IE1: Cross Site Scripting Attempt detected. IP: @ip, Path: @path, Findings Count: @count',
      [
        '@ip' => $request->headers->all()['x-real-ip'][0] ?? $request->getClientIp(),
        '@path' => $request->getPathInfo(),
        '@count' => count($findings),
        '@details' => $findings,
      ]
    );
  }
}
