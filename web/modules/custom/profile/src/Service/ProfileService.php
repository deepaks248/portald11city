<?php

namespace Drupal\profile\Service;

use Drupal\Component\Serialization\Json;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ProfileService
{

    protected $httpClient;

    public function __construct($http_client)
    {
        $this->httpClient = $http_client;
    }

    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('http_client')
        );
    }

    public function fetchFamilyMembers($user_id)
    {
        try {
            $global_service = \Drupal::service('global_module.global_variables');
            $globalVariables = $global_service->getGlobalVariables();
            $access_token = $global_service->getApimanAccessToken();

            $session = \Drupal::request()->getSession();

            $url = $globalVariables['apiManConfig']['config']['apiUrl'] .
                'tiotcitizenapp' .
                $globalVariables['apiManConfig']['config']['apiVersion'] .
                'family-members/fetch-family-member';
            $response = $this->httpClient->get($url . '/' . $user_id, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept' => 'application/json', // preferred over Content-Type for GET
                ],
            ]);
            \Drupal::logger('profile')->debug('URL: @url, Payload: @payload', [
                '@url' => $url,
                '@payload' => json_encode(['userId' => $user_id]),
            ]);

            $body = $response->getBody()->getContents();

            $data = Json::decode($body);
            if (isset($data['data']) && is_array($data['data'])) {
                foreach ($data['data'] as &$contact) {
                    // Decrypt and mask email
                    if (!empty($contact['email'])) {
                        $contact['email'] = $global_service->decrypt($contact['email']);
                        $emailParts = explode('@', $contact['email']);
                        if (count($emailParts) === 2) {
                            $contact['email'] = substr($emailParts[0], 0, 3) . str_repeat('*', 4) . '@' . $emailParts[1];
                        }
                    }

                    // Decrypt and mask contact number — 🔄 moved INSIDE the loop
                    if (!empty($contact['contact'])) {
                        $contact['contact'] = $global_service->decrypt($contact['contact']);
                        $contact['contact'] = str_repeat('*', max(0, strlen($contact['contact']) - 4)) . substr($contact['contact'], -4);
                    }
                }
            }

            return $data['data'] ?? [];

        } catch (RequestException $e) {
            \Drupal::logger('profile')->error('Family fetch error: @message', ['@message' => $e->getMessage()]);
            return [];
        }
    }

}
