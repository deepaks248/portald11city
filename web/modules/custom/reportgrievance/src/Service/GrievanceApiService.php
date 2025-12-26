<?php

namespace Drupal\reportgrievance\Service;

use GuzzleHttp\ClientInterface;
use Drupal\global_module\Service\GlobalVariablesService;

class GrievanceApiService
{

  protected $httpClient;
  protected $secret = 'replace_with_your_secret_key';
  protected $globalVariablesService;

  public function __construct(ClientInterface $http_client, GlobalVariablesService $global_variables_service)
  {
    $this->httpClient = $http_client;
    $this->globalVariablesService = $global_variables_service;
  }

  protected function generateChecksum(array $data): string
  {
    ksort($data);
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return hash_hmac('sha256', $json, $this->secret);
  }

  protected function getCsrfToken(): string
  {
    return \Drupal::service('csrf_token')->get('');
  }

  public function getIncidentTypes()
  {
    $globalVariables = $this->globalVariablesService->getGlobalVariables();
    $accessToken = $this->globalVariablesService->getApimanAccessToken();

    $incidentTypeUrl = $globalVariables['apiManConfig']['config']['apiUrl'] . 'trinityengage-casemanagementsystem' . $globalVariables['apiManConfig']['config']['apiVersion'] . 'master-data/incident-types';

    $response = $this->httpClient->request('GET', $incidentTypeUrl, [
      'query' => [
        'tenantCode' => $globalVariables['applicationConfig']['config']['ceptenantCode'],
      ],
      'headers' => [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $accessToken,
      ],
      'verify' => FALSE,
    ]);

    $data = json_decode($response->getBody(), TRUE);
    $options = [];
    if (!empty($data['data'])) {
      foreach ($data['data'] as $type) {
        $options[$type['incidentTypeId']] = $type['incidentType'];
      }
    }

    return $options;
  }

  public function getIncidentSubTypes(int $incidentTypeId): array
  {
    $globalVariables = $this->globalVariablesService->getGlobalVariables();

    $accessToken = $this->globalVariablesService->getApimanAccessToken();

    if (!$accessToken) {
      \Drupal::logger('report_grievance')->error('Apiman access token could not be retrieved.');
      return [];
    }

    $incidentSubTypeUrl = $globalVariables['apiManConfig']['config']['apiUrl'] . 'trinityengage-casemanagementsystem' . $globalVariables['apiManConfig']['config']['apiVersion'] . 'master-data/incident-sub-types';

    $response = $this->httpClient->request('GET', $incidentSubTypeUrl, [
      'query' => [
        'tenantCode' => $globalVariables['applicationConfig']['config']['ceptenantCode'],
        'incidentTypeId' => $incidentTypeId,
      ],
      'headers' => [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $accessToken,
      ],
      'verify' => FALSE,
    ]);

    $data = json_decode($response->getBody(), TRUE);
    $options = [];
    if (!empty($data['data'])) {
      foreach ($data['data'] as $subType) {
        $options[$subType['incidentSubTypeId']] = $subType['incidentSubType'];
      }
    }

    return $options;
  }

  public function sendGrievance(array $data): array
  {
    $checksum = $this->generateChecksum($data);
    $csrf_token = $this->getCsrfToken();

    $globalVariables = $this->globalVariablesService->getGlobalVariables();
    $accessToken = $this->globalVariablesService->getApimanAccessToken();

    $grivanceUrl = $globalVariables['apiManConfig']['config']['apiUrl'] . 'trinityengage-casemanagementsystem' . $globalVariables['apiManConfig']['config']['apiVersion'] . 'grievance-manage/report-grievance';

    try {
      $response = $this->httpClient->request('POST', $grivanceUrl, [
        'headers' => [
          'Content-Type' => 'application/json',
          'Authorization' => 'Bearer ' . $accessToken,
          'X-CSRF-Token' => $csrf_token,
          'X-Checksum' => $checksum,
        ],
        'json' => $data,
        'timeout' => 10,
      ]);

      $body = json_decode($response->getBody()->getContents(), TRUE);
      return ['success' => true, 'data' => $body];
    } catch (\Exception $e) {
      \Drupal::logger('report_grievance')->error('API submission failed: @message', ['@message' => $e->getMessage()]);
      return ['success' => false];
    }
  }
}
