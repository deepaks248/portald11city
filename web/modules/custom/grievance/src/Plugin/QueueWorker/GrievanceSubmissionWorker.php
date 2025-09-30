<?php

// namespace Drupal\grievance\Plugin\QueueWorker;

// use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
// use Drupal\Core\Queue\QueueWorkerBase;
// use Drupal\Core\Logger\LoggerChannelFactoryInterface;
// use Drupal\grievance\Service\GrievanceReportApiService;
// use Symfony\Component\DependencyInjection\ContainerInterface;

// /**
//  * Processes items for grievance submission.
//  *
//  * @QueueWorker(
//  *   id = "grievance_submission",
//  *   title = @Translation("Grievance Submission Worker"),
//  *   cron = {"time" = 60}
//  * )
//  */
// class GrievanceSubmissionWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

//   protected $grievanceService;
//   protected $logger;

//   public function __construct(
//     array $configuration,
//     $plugin_id,
//     $plugin_definition,
//     GrievanceReportApiService $grievanceService,
//     LoggerChannelFactoryInterface $logger_factory
//   ) {
//     parent::__construct($configuration, $plugin_id, $plugin_definition);
//     $this->grievanceService = $grievanceService;
//     $this->logger = $logger_factory->get('report_grievance');
//   }

//   /**
//    * {@inheritdoc}
//    */
//   public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
//     return new static(
//       $configuration,
//       $plugin_id,
//       $plugin_definition,
//       $container->get('grievance.grievance_api'),
//       $container->get('logger.factory')
//     );
//   }

//   /**
//    * {@inheritdoc}
//    */
//   public function processItem($data) {
//     try {
//       $result = $this->grievanceService->sendGrievance($data);
//       if ($result['success']) {
//         $this->logger->info('Grievance submitted successfully for userId @uid', ['@uid' => $data['userId']]);
//       }
//       else {
//         $this->logger->error('Grievance submission failed for userId @uid', ['@uid' => $data['userId']]);
//       }
//     }
//     catch (\Exception $e) {
//       $this->logger->error('Queue grievance submission error: @msg', ['@msg' => $e->getMessage()]);
//     }
//   }
// }

// namespace Drupal\grievance\Plugin\QueueWorker;

// use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
// use Drupal\Core\Queue\QueueWorkerBase;
// use Drupal\Core\Logger\LoggerChannelFactoryInterface;
// use Drupal\grievance\Service\GrievanceReportApiService;
// use Symfony\Component\DependencyInjection\ContainerInterface;

// /**
//  * Processes items for grievance submission asynchronously.
//  *
//  * @QueueWorker(
//  *   id = "grievance_submission",
//  *   title = @Translation("Grievance Submission Worker"),
//  *   cron = {"time" = 60}
//  * )
//  */
// class GrievanceSubmissionWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface
// {

//     protected $grievanceService;
//     protected $logger;

//     public function __construct(
//         array $configuration,
//         $plugin_id,
//         $plugin_definition,
//         GrievanceReportApiService $grievanceService,
//         LoggerChannelFactoryInterface $logger_factory
//     ) {
//         parent::__construct($configuration, $plugin_id, $plugin_definition);
//         $this->grievanceService = $grievanceService;
//         $this->logger = $logger_factory->get('report_grievance');
//     }

//     /**
//      * {@inheritdoc}
//      */
//     public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
//     {
//         return new static(
//             $configuration,
//             $plugin_id,
//             $plugin_definition,
//             $container->get('grievance.grievance_api'),
//             $container->get('logger.factory')
//         );
//     }

//     //   /**
//     //    * {@inheritdoc}
//     //    */
//     //   public function processItem($data) {
//     //     try {
//     //       // Fire async API call
//     //       $promise = $this->grievanceService->sendGrievanceAsync($data);
//     //         \Drupal::logger('report_grievance')->info('Async response: @promise', [
//     //           '@promise' => print_r($promise, TRUE),
//     //       ]);
//     //       // Attach success and failure callbacks
//     //       $promise->then(
//     //         function ($response) use ($data) {
//     //           $body = json_decode($response->getBody()->getContents(), TRUE);
//     //           $this->logger->info(
//     //             'Async grievance submitted successfully for userId @uid: @response',
//     //             ['@uid' => $data['userId'], '@response' => print_r($body, TRUE)]
//     //           );
//     //         },
//     //         function ($exception) use ($data) {
//     //           $this->logger->error(
//     //             'Async grievance submission failed for userId @uid: @msg',
//     //             ['@uid' => $data['userId'], '@msg' => $exception->getMessage()]
//     //           );

//     //           // Push failed grievance to a retry queue
//     //           $retryQueue = \Drupal::queue('grievance_retry_queue');
//     //           $retryQueue->createItem($data);
//     //         }
//     //       );

//     //       // Optionally wait for completion (blocking), or leave fire-and-forget
//     //       // $promise->wait();
//     //     }
//     //     catch (\Exception $e) {
//     //       $this->logger->error('Queue grievance submission error: @msg', ['@msg' => $e->getMessage()]);
//     //     }
//     //   }
//     public function processItem($data)
//     {
//         try {
//             // Fire async API call
//             $promise = $this->grievanceService->sendGrievanceAsync($data);

//             // Attach success and failure callbacks
//             $promise->then(
//                 function ($response) use ($data) {
//                     $body = json_decode($response->getBody()->getContents(), TRUE);
//                     $this->logger->info(
//                         'Async grievance submitted successfully for userId @uid: @response',
//                         [
//                             '@uid' => $data['userId'],
//                             '@response' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
//                         ]
//                     );
//                 },
//                 function ($exception) use ($data) {
//                     $this->logger->error(
//                         'Async grievance submission failed for userId @uid: @msg',
//                         [
//                             '@uid' => $data['userId'],
//                             '@msg' => $exception->getMessage(),
//                         ]
//                     );

//                     // Push failed grievance to a retry queue
//                     $retryQueue = \Drupal::queue('grievance_retry_queue');
//                     $retryQueue->createItem($data);
//                 }
//             );

//             // Optional: wait for completion (blocking), only if needed
//             $promise->wait();

//         } catch (\Exception $e) {
//             $this->logger->error(
//                 'Queue grievance submission error: @msg',
//                 ['@msg' => $e->getMessage()]
//             );
//         }
//     }
// }


// namespace Drupal\grievance\Plugin\QueueWorker;

// use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
// use Drupal\Core\Queue\QueueWorkerBase;
// use Drupal\Core\Logger\LoggerChannelFactoryInterface;
// use Drupal\grievance\Service\GrievanceReportApiService;
// use Symfony\Component\DependencyInjection\ContainerInterface;

// /**
//  * Processes grievances asynchronously with guaranteed execution and logging.
//  *
//  * @QueueWorker(
//  *   id = "grievance_submission",
//  *   title = @Translation("Grievance Submission Worker"),
//  *   cron = {"time" = 60}
//  * )
//  */
// class GrievanceSubmissionWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface
// {

//     protected GrievanceReportApiService $grievanceService;
//     protected $logger;

//     public function __construct(
//         array $configuration,
//         $plugin_id,
//         $plugin_definition,
//         GrievanceReportApiService $grievanceService,
//         LoggerChannelFactoryInterface $logger_factory
//     ) {
//         parent::__construct($configuration, $plugin_id, $plugin_definition);
//         $this->grievanceService = $grievanceService;
//         $this->logger = $logger_factory->get('report_grievance');
//     }

//     public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
//     {
//         return new static(
//             $configuration,
//             $plugin_id,
//             $plugin_definition,
//             $container->get('grievance.grievance_api'),
//             $container->get('logger.factory')
//         );
//     }

//     //   /**
//     //    * {@inheritdoc}
//     //    */
//     //   public function processItem($data) {
//     //     try {
//     //       // Fire async request
//     //       $promise = $this->grievanceService->sendGrievanceAsync($data);

//     //       // Wait for it to resolve (ensures logging works in QueueWorker)
//     //       $response = $promise->wait();
//     //       $body = json_decode($response->getBody()->getContents(), TRUE);

//     //       $this->logger->info(
//     //         'Grievance submitted successfully for userId @uid: @response',
//     //         ['@uid' => $data['userId'], '@response' => json_encode($body)]
//     //       );
//     //     }
//     //     catch (\Exception $e) {
//     //       $this->logger->error(
//     //         'Grievance submission failed for userId @uid: @msg',
//     //         ['@uid' => $data['userId'], '@msg' => $e->getMessage()]
//     //       );

//     //       // Push failed grievance to retry queue
//     //       \Drupal::queue('grievance_retry_queue')->createItem($data);
//     //     }
//     //   }

//     public function processItem($data)
//     {
//         try {
//             // Fire async request
//             $promise = $this->grievanceService->sendGrievanceAsync($data);

//             // Wait for completion when running via cron (ensures logging works)
//             $response = $promise->wait();
//             $body = json_decode($response->getBody()->getContents(), TRUE);

//             $this->logger->info(
//                 'Grievance submitted successfully for userId @uid: @response',
//                 ['@uid' => $data['userId'], '@response' => json_encode($body)]
//             );
//         } catch (\Exception $e) {
//             $this->logger->error(
//                 'Grievance submission failed for userId @uid: @msg',
//                 ['@uid' => $data['userId'], '@msg' => $e->getMessage()]
//             );

//             // Push failed grievance to retry queue
//             \Drupal::queue('grievance_retry_queue')->createItem($data);
//         }
//     }
// }


// namespace Drupal\grievance\Plugin\QueueWorker;

// use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
// use Drupal\Core\Queue\QueueWorkerBase;
// use Drupal\Core\Logger\LoggerChannelFactoryInterface;
// use Drupal\grievance\Service\GrievanceReportApiService;
// use Symfony\Component\DependencyInjection\ContainerInterface;
// use GuzzleHttp\Promise;

// /**
//  * Processes grievances asynchronously with concurrency.
//  *
//  * @QueueWorker(
//  *   id = "grievance_submission",
//  *   title = @Translation("Grievance Submission Worker"),
//  *   cron = {"time" = 60}
//  * )
//  */
// class GrievanceSubmissionWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

//   protected GrievanceReportApiService $grievanceService;
//   protected $logger;

//   public function __construct(
//     array $configuration,
//     $plugin_id,
//     $plugin_definition,
//     GrievanceReportApiService $grievanceService,
//     LoggerChannelFactoryInterface $logger_factory
//   ) {
//     parent::__construct($configuration, $plugin_id, $plugin_definition);
//     $this->grievanceService = $grievanceService;
//     $this->logger = $logger_factory->get('report_grievance');
//   }

//   public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
//     return new static(
//       $configuration,
//       $plugin_id,
//       $plugin_definition,
//       $container->get('grievance.grievance_api'),
//       $container->get('logger.factory')
//     );
//   }

//   /**
//    * {@inheritdoc}
//    */
//   public function processItem($data) {
//     // This won't be used directly — we’ll batch process in cron instead.
//   }

//   /**
//    * Process multiple queue items concurrently.
//    */
//   public function processBatch($limit = 10, $concurrency = 5) {
//     $queue = \Drupal::queue('grievance_submission');
//     $items = [];

//     // Claim up to $limit items
//     for ($i = 0; $i < $limit; $i++) {
//       if ($item = $queue->claimItem()) {
//         $items[] = $item;
//       }
//       else {
//         break;
//       }
//     }

//     if (empty($items)) {
//       return;
//     }

//     $promises = [];
//     foreach ($items as $item) {
//       $promises[] = $this->grievanceService
//         ->sendGrievanceAsync($item->data)
//         ->then(
//           function ($response) use ($item, $queue) {
//             $queue->deleteItem($item);
//             $body = json_decode($response->getBody()->getContents(), TRUE);
//             $this->logger->info(
//               'Grievance submitted concurrently for userId @uid: @response',
//               ['@uid' => $item->data['userId'], '@response' => json_encode($body)]
//             );
//           },
//           function ($exception) use ($item, $queue) {
//             $queue->releaseItem($item);
//             $this->logger->error(
//               'Concurrent grievance submission failed for userId @uid: @msg',
//               ['@uid' => $item->data['userId'], '@msg' => $exception->getMessage()]
//             );
//           }
//         );
//     }

//     // Execute with concurrency control
//     Promise\each_limit($promises, $concurrency)->wait();
//   }
// }

namespace Drupal\grievance\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\grievance\Service\GrievanceReportApiService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Promise\Utils;

/**
 * Processes grievances asynchronously with concurrency.
 *
 * @QueueWorker(
 *   id = "grievance_submission",
 *   title = @Translation("Grievance Submission Worker"),
 *   cron = {"time" = 60}
 * )
 */
class GrievanceSubmissionWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface
{

    protected GrievanceReportApiService $grievanceService;
    protected $logger;
    protected QueueFactory $queueFactory;

    public function __construct(
        array $configuration,
        $plugin_id,
        $plugin_definition,
        GrievanceReportApiService $grievanceService,
        LoggerChannelFactoryInterface $logger_factory,
        QueueFactory $queueFactory
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->grievanceService = $grievanceService;
        $this->logger = $logger_factory->get('report_grievance');
        $this->queueFactory = $queueFactory;
    }

    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
    {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('grievance.grievance_api'),
            $container->get('logger.factory'),
            $container->get('queue')
        );
    }

    /**
     * {@inheritdoc}
     * Provides a safe single-item fallback.
     */
    public function processItem($data)
    {
        try {
            $response = $this->grievanceService->sendGrievance($data);
            $this->logger->info(
                'Grievance submitted (single) for userId @uid: @response',
                [
                    '@uid' => $data['userId'] ?? 'N/A',
                    '@response' => json_encode($response),
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'Single grievance submission failed for userId @uid: @msg',
                [
                    '@uid' => $data['userId'] ?? 'N/A',
                    '@msg' => $e->getMessage(),
                ]
            );
            throw $e;
        }
    }

    /**
     * Process multiple queue items concurrently.
     *
     * @param int $limit
     *   Maximum items to claim in one batch.
     * @param int $concurrency
     *   Maximum concurrent requests.
     */
    public function processBatch($limit = 10, $concurrency = 5)
    {
        $queue = $this->queueFactory->get('grievance_submission');
        $items = [];

        // Claim up to $limit items.
        for ($i = 0; $i < $limit; $i++) {
            if ($item = $queue->claimItem()) {
                $items[] = $item;
            } else {
                break;
            }
        }

        if (empty($items)) {
            return;
        }

        $promises = [];
        foreach ($items as $item) {
            $promises[] = $this->grievanceService
                ->sendGrievanceAsync($item->data)
                ->then(
                    function ($response) use ($item, $queue) {
                        $queue->deleteItem($item);
                        $body = json_decode($response->getBody()->getContents(), TRUE);
                        $this->logger->info(
                            'Grievance submitted concurrently for userId @uid: @response',
                            [
                                '@uid' => $item->data['userId'] ?? 'N/A',
                                '@response' => json_encode($body),
                            ]
                        );
                    },
                    function ($exception) use ($item, $queue) {
                        $queue->releaseItem($item);
                        $this->logger->error(
                            'Concurrent grievance submission failed for userId @uid: @msg',
                            [
                                '@uid' => $item->data['userId'] ?? 'N/A',
                                '@msg' => $exception->getMessage(),
                            ]
                        );
                    }
                );
        }

        // Execute with concurrency control.
        // Execute with concurrency control
        Utils::eachLimit($promises, $concurrency)->wait();
    }
}
