<?php

namespace Drupal\global_module\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\global_module\Service\GlobalVariablesService;

class FileUploadService
{

    protected $uuidService;
    protected $globalVariablesService;

    /**
     * Constructor.
     */
    public function __construct(UuidInterface $uuid_service, GlobalVariablesService $global_variables_service)
    {
        $this->uuidService = $uuid_service;
        $this->globalVariablesService = $global_variables_service;
    }

    /**
     * Uploads a file to a remote server.
     */
    public function uploadFile(Request $request)
    {
        define('UPLOAD_FILE', 'uploadedfile1');
        // dump($_FILES['files']['full_path']['upload_file']);
        $file = $_FILES['files']['full_path']['upload_file'] ?? null;
        if (!$file) {
            return new JsonResponse([
                'status' => false,
                'message' => 'No file uploaded.',
            ], 400);
        }
        // dump(($file['name']['upload_file']));
        $extension = pathinfo($_FILES['files']['name']['upload_file'], PATHINFO_EXTENSION);
        $extn = explode(".", $_FILES['files']['name']['upload_file']);
        $fileMime = mime_content_type($_FILES['files']['tmp_name']['upload_file']);
        // dump($fileMime);
        // dump($extn);
        // dump($extension);
        // exit();

        $allowedTypes = [
            'image/jpeg',
            'image/png',
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'video/mp4',
        ];

        if (!in_array($fileMime, $allowedTypes)) {
            return new JsonResponse([
                'status' => false,
                'message' => 'File content not allowed!',
            ]);
        }

        if (count($extn) > 2) {
            return new JsonResponse([
                'message' => 'Multiple file extensions not allowed',
                'status' => false,
            ]);
        }

        $ext = strtolower($extn[1] ?? '');
        $fileTypeVal = null;
        $fileTypeType = '';
        $fileTypeValid = false;

        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
            $fileTypeVal = 2;
            $fileTypeType = "image";
            $fileTypeValid = true;
        } elseif (in_array($ext, ['pdf', 'doc', 'docx', 'mp3', 'xlsx'])) {
            $fileTypeVal = 4;
            $fileTypeType = "file";
            $fileTypeValid = true;
        } elseif ($ext == 'mp4') {
            $fileTypeVal = 1;
            $fileTypeType = "video";
            $fileTypeValid = true;
        }

        $uuidFilename = $this->uuidService->generate() . '.' . $extension;
        // dump($fileTypeValid);
        if ($fileTypeValid) {
            $cfile = curl_file_create($_FILES['files']['tmp_name']['upload_file'], $_FILES['files']['type']['upload_file'], $uuidFilename);
            $postRequest = [
                UPLOAD_FILE => $cfile,
                'success_action_status' => 200,
            ];
            //   dump($cfile);
            $globals = $this->globalVariablesService->getGlobalVariables();
            $fileUplPath = $globals['applicationConfig']['config']['fileuploadPath'] ?? NULL;
            //   dump($fileUplPath);

            if (!$fileUplPath) {
                return new JsonResponse(['error' => 'Upload path not configured in Vault.'], 500);
            }


            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $fileUplPath . 'upload_media_test1.php',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $postRequest,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            // dump($curl);
            
            $response = curl_exec($curl);
            $curl_error = curl_error($curl);
            curl_close($curl);
            // dump($response);
            // exit();

            if ($response === false) {
                return new JsonResponse(['error' => $curl_error], 500);
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                return new JsonResponse(['error' => 'Invalid JSON response'], 500);
            }

            // dump([
            //     'fileName' => $fileUplPath . $uuidFilename,
            //     'fileTypeId' => $fileTypeVal,
            //     'fileTypeVal' => $fileTypeType,
            // ]);
            return new JsonResponse([
                'fileName' => $fileUplPath . $uuidFilename,
                'fileTypeId' => $fileTypeVal,
                'fileTypeVal' => $fileTypeType,
            ]);
        }

        return new JsonResponse([
            'message' => 'Selected file not allowed!',
            'status' => false,
        ]);
    }
}
