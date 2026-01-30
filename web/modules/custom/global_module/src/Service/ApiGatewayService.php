<?php

namespace Drupal\global_module\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\global_module\Service\VaultConfigService;
use Drupal\global_module\Service\ApiHttpClientService;
use Drupal\user\Entity\User;

class ApiGatewayService
{

    public const PAYLOADS = 'payloads';

    protected $vaultConfigService;
    protected $apiHttpClientService;

    /**
     * Constructor.
     */
    public function __construct(
        VaultConfigService $vaultConfigService,
        ApiHttpClientService $apiHttpClientService
    ) {
        $this->vaultConfigService = $vaultConfigService;
        $this->apiHttpClientService = $apiHttpClientService;
    }

    public function getServiceUrl(string $serviceName): string
    {
        $globalVariables = $this->vaultConfigService->getGlobalVariables();

        $apiUrl        = $globalVariables['apiManConfig']['config']['apiUrl'];
        $apiVer        = $globalVariables['apiManConfig']['config']['apiVersion'];
        $webportalUrl  = $globalVariables['applicationConfig']['config']['webportalUrl'];

        $serviceMap = [
            'cep'                => 'trinityengage-casemanagementsystem',
            'cad'                => 'trinity-respond',
            'ngcad'              => 'ngcadmobileapp',
            'iot'                => 'tiotIOTPS',
            'cityapp'            => 'tengageCity',
            'idam'               => 'UMA',
            'tiotweb'            => 'tiotweb',
            'tiotICCCOperator'   => 'tiotICCCOperator',
            'tiotcitizenapp'     => 'tiotcitizenapp',
            'innv'               => 'tiotcitizenapp',
        ];

        if ($serviceName === 'portal') {
            return $webportalUrl;
        }

        return isset($serviceMap[$serviceName])
            ? $apiUrl . $serviceMap[$serviceName] . $apiVer
            : '';
    }

    private function buildServiceUrl(array $data): ?string
    {
        $base = $this->getServiceUrl($data['service'] ?? '');
        return $base ? $base . ($data['endPoint'] ?? '') : NULL;
    }

    public function postData(Request $request): JsonResponse
    {
        $statusCode = 200;
        $response   = [];

        try {
            if ($request->getMethod() !== 'POST') {
                throw new \LogicException('Method not allowed', 405);
            }

            $postData = json_decode($request->getContent(), TRUE);

            if (empty($postData['service']) || empty($postData['type'])) {
                throw new \InvalidArgumentException('Invalid payload', 400);
            }

            $url      = $this->buildServiceUrl($postData);
            $response = $this->handleRequestByType($postData, $url, $request);
        } catch (\Throwable $e) {
            $statusCode = $e->getCode() ?: 500;

            \Drupal::logger('post_data')->error($e->getMessage());

            $response = [
                'status'  => FALSE,
                'message' => $e->getMessage() ?: 'Internal server error.',
            ];
        }

        return new JsonResponse($response, $statusCode);
    }

    public function handleRequestByType(
        array $data,
        string $url,
        Request $request,
    ): array {
        $session   = $request->getSession();
        $userData  = $session->get('api_redirect_result') ?? [];
        $type      = $data['type'] ?? NULL;
        $payload   = $data[self::PAYLOADS] ?? [];

        $handlers = [
            2 => fn() =>
            $this->apiHttpClientService->postApiman($url, $payload),

            'delyUser' => fn() =>
            $this->userDelete(
                userID: $userData['userId'] ?? NULL,
                tenantCode: $userData['tenantCode'] ?? NULL
            ),
        ];

        return isset($handlers[$type])
            ? $handlers[$type]()
            : $this->apiHttpClientService->postApiman($url, $payload);
    }

    public function userDelete(int $userID, string $tenantCode): array
    {
        $globals = $this->vaultConfigService->getGlobalVariables();

        $apiUrl = $globals['apiManConfig']['config']['apiUrl'];
        $apiVer = $globals['apiManConfig']['config']['apiVersion'];

        $cityDeleteUrl = $globals['applicationConfig']['config']['deleteAPICA'] . $userID;
        \Drupal::logger('City App Delete Url')->notice($cityDeleteUrl);

        if (!$this->deleteFromCityApp($cityDeleteUrl)) {
            return [
                'status'  => FALSE,
                'message' => 'Failed to delete user account.',
            ];
        }

        if (!$this->deleteFromCEP($apiUrl, $apiVer, $userID, $tenantCode)) {
            return [
                'status'  => FALSE,
                'message' => 'Failed to delete user from case management system.',
            ];
        }

        $this->deleteDrupalAccount();

        return [
            'status'  => TRUE,
            'message' => 'User account deleted successfully!',
        ];
    }

    private function deleteFromCityApp(string $url): bool
    {
        $response = $this->apiHttpClientService->postApi($url);
        \Drupal::logger('Post Data response')->notice(print_r($response, TRUE));

        return !empty($response['status']);
    }

    private function deleteFromCEP(
        string $apiUrl,
        string $apiVer,
        int $userID,
        string $tenantCode
    ): bool {
        $url = sprintf(
            '%strinityengage-casemanagementsystem%suser/delete-user?userId=%d&tenantCode=%s',
            $apiUrl,
            $apiVer,
            $userID,
            $tenantCode
        );

        $response = $this->apiHttpClientService->postApiman($url);
        \Drupal::logger('CEP Delete API response')->notice(print_r($response, TRUE));

        return !empty($response['status']);
    }

    private function deleteDrupalAccount(): void
    {
        $account = User::load(\Drupal::currentUser()->id());
        if ($account) {
            $account->delete();
        }
    }
}
