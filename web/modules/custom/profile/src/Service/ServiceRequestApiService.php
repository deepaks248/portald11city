<?php

namespace Drupal\profile\Service;

use GuzzleHttp\ClientInterface;
use Drupal\global_module\Service\GlobalVariablesService;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

class ServiceRequestApiService
{
    protected ClientInterface $httpClient;
    protected GlobalVariablesService $globalVariablesService;
    protected LoggerChannelFactoryInterface $loggerFactory;

    public function __construct(
        ClientInterface $http_client,
        GlobalVariablesService $global_variables_service,
        LoggerChannelFactoryInterface $loggerFactory
    ) {
        $this->httpClient = $http_client;
        $this->globalVariablesService = $global_variables_service;
        $this->loggerFactory = $loggerFactory; // Use LoggerChannelFactoryInterface
    }

    /**
     * Helper function to get API URL.
     */
    private function getApiUrl(string $endpoint): string
    {
        $globalVariables = $this->globalVariablesService->getGlobalVariables();
        $apiUrl = $globalVariables['apiManConfig']['config']['apiUrl'];
        $apiVersion = $globalVariables['apiManConfig']['config']['apiVersion'];
        return $apiUrl . 'trinityengage-casemanagementsystem' . $apiVersion . $endpoint;
    }

    /**
     * Helper function to prepare headers for the API request.
     */
    private function getHeaders(): array
    {
        $accessToken = $this->globalVariablesService->getApimanAccessToken();
        return [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Executes a GET request and handles errors.
     */
    private function executeGetRequest(string $url, array $options = []): array
    {
        try {
            $response = $this->httpClient->request('GET',$url, $options);
            $data = json_decode($response->getBody(), TRUE);
            return $data ?? [];
        } catch (\Exception $e) {
            $logger = $this->loggerFactory->get('service_request'); // Get the logger channel
            $logger->error('Exception while making GET request: @message', ['@message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Executes a POST request and handles errors.
     */
    private function executePostRequest(string $url, array $body): array
    {
        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => $this->getHeaders(),
                'json' => $body,
            ]);
            $data = json_decode($response->getBody(), TRUE);
            return $data ?? [];
        } catch (\Exception $e) {
            $logger = $this->loggerFactory->get('service_request'); // Get the logger channel
            $logger->error('Exception while making POST request: @message', ['@message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get service request details by grievanceId and requestTypeId.
     */
    public function getServiceRequestDetails(string $grievanceId, int $requestTypeId): array
    {
        $url = $this->getApiUrl('common/service-request-by-grievance?grievanceId=' . $grievanceId . '&requestTypeId=' . $requestTypeId);
        $options = [
            'headers' => $this->getHeaders(),
        ];
        return $this->executeGetRequest($url, $options);
    }

    /**
     * Get service requests with pagination and optional search term.
     */
    public function getServiceRequests(int $userId, int $page = 1, int $itemsPerPage = 10, string $searchTerm = ''): array
    {
        $url = $this->getApiUrl('common/service-request');
        $body = [
            'tenantCode' => 'fireppr',
            'search' => $searchTerm,
            'pageNumber' => $page,
            'itemsPerPage' => $itemsPerPage,
            'userId' => $userId,
            'requestTypeId' => 1,
            'orderBy' => '1',
            'orderByfield' => '1',
        ];
        return $this->executePostRequest($url, $body);
    }
}
