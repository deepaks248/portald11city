<?php

namespace Drupal\profile\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\profile\Service\ServiceRequestApiService;

class RequestDetailsController extends ControllerBase
{

    protected ServiceRequestApiService $service;

    public function __construct(ServiceRequestApiService $service)
    {
        $this->service = $service;
    }

    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('profile.service_request_api')
        );
    }

    //   public function view(string $id, Request $request) {
    //     // Reconstruct full grievance ID
    //     $grievanceId = 'GV-' . date('Ymd') . '-' . $id;

    //     // Fetch the requestTypeId via search (if needed) or pass it as a query param
    //     $requestTypeId = $request->query->get('type', 1);

    //     // Fetch live request details
    //     $api_response = $this->service->getServiceRequestDetails($grievanceId, $requestTypeId);

    //     if (empty($api_response['data'])) {
    //       return [
    //         '#markup' => $this->t('No data found for grievance ID @id.', ['@id' => $grievanceId]),
    //       ];
    //     }

    //     return [
    //       '#theme' => 'request_details_page',
    //       '#data' => $api_response['data'],
    //     ];
    //   }
    public function view(string $grievance_id, Request $request)
    {
        $requestTypeId = $request->query->get('type', 1);

        $api_response = $this->service->getServiceRequestDetails($grievance_id, $requestTypeId);

        if (empty($api_response['data'])) {
            return [
                '#markup' => $this->t('No data found for grievance ID @id.', ['@id' => $grievance_id]),
            ];
        }

        return [
            '#theme' => 'request_details_page',
            '#data' => $api_response['data'],
            '#cache' => ['max-age' => 0],
        ];
    }
}
