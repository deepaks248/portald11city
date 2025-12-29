<?php

namespace Drupal\profile\Service;

use GuzzleHttp\ClientInterface;
use Drupal\Core\Site\Settings;
use Drupal\global_module\Service\GlobalVariablesService;

class ServiceRequestApiService
{

    protected ClientInterface $httpClient;
    protected $globalVariablesService;


    public function __construct(ClientInterface $http_client, GlobalVariablesService $global_variables_service)
    {
        $this->httpClient = $http_client;
        $this->globalVariablesService = $global_variables_service;
    }

    public function getServiceRequestDetails(string $grievanceId, int $requestTypeId): array
    {
        $globalVariables = $this->globalVariablesService->getGlobalVariables();
        $accessToken = $this->globalVariablesService->getApimanAccessToken();
        
        $req_detail_url = $globalVariables['apiManConfig']['config']['apiUrl'] . 'trinityengage-casemanagementsystem' . $globalVariables['apiManConfig']['config']['apiVersion'] . 'common/service-request-by-grievance?grievanceId=' . $grievanceId . '&requestTypeId=' . $requestTypeId;
        $options = [
            'headers' => [
                'accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ],
        ];

        try {
            $client = \Drupal::httpClient();
            $response = $client->get($req_detail_url, $options);
            $data = json_decode($response->getBody(), TRUE);
            return $data ?? [];
        } catch (\Exception $e) {
            watchdog_exception('profile', $e);
            return [];
        }
    }


    public function getServiceRequests($userId, $page = 1, $itemsPerPage = 10, $searchTerm = '')
    {
        $globalVariables = $this->globalVariablesService->getGlobalVariables();
        $accessToken = $this->globalVariablesService->getApimanAccessToken();
        $service_req_url = $globalVariables['apiManConfig']['config']['apiUrl'] . 'trinityengage-casemanagementsystem' . $globalVariables['apiManConfig']['config']['apiVersion'] . 'common/service-request';
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
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

        $response = $this->httpClient->request('POST', $service_req_url, [
            'headers' => $headers,
            'json' => $body,
        ]);

        return json_decode($response->getBody(), TRUE);
    }
}
