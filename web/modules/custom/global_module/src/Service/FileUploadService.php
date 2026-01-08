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
        $file = $_FILES['files']['full_path']['upload_file'] ?? NULL;
        if (!$file) {
            return new JsonResponse([
                'status' => FALSE,
                'message' => 'No file uploaded.',
            ], 400);
        }
        $extension = pathinfo($_FILES['files']['name']['upload_file'], PATHINFO_EXTENSION);
        $extn = explode(".", $_FILES['files']['name']['upload_file']);
        $fileMime = mime_content_type($_FILES['files']['tmp_name']['upload_file']);

        $allowedTypes = [
            'image/jpeg',
            'image/png',
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'video/mp4',
        ];

        if (!in_array($fileMime, $allowedTypes)) {
            return new JsonResponse([
                'status' => FALSE,
                'message' => 'File content not allowed!',
            ]);
        }

        if (count($extn) > 2) {
            return new JsonResponse([
                'message' => 'Multiple file extensions not allowed',
                'status' => FALSE,
            ]);
        }

        $ext = strtolower($extn[1] ?? '');
        $fileTypeVal = NULL;
        $fileTypeType = '';
        $fileTypeValid = FALSE;

        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
            $fileTypeVal = 2;
            $fileTypeType = "image";
            $fileTypeValid = TRUE;
        } elseif (in_array($ext, ['pdf', 'doc', 'docx', 'mp3', 'xlsx'])) {
            $fileTypeVal = 4;
            $fileTypeType = "file";
            $fileTypeValid = TRUE;
        } elseif ($ext == 'mp4') {
            $fileTypeVal = 1;
            $fileTypeType = "video";
            $fileTypeValid = TRUE;
        }
        $fileTmp = $_FILES['files']['tmp_name']['upload_file'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($fileTmp);
        \Drupal::logger('file_upload')->info('Detected MIME type: @mime', ['@mime' => $mimeType]);
        // ✅ Content validation
        if (strpos($mimeType, 'image/') === 0) {
            // Image validation
            $imgInfo = getimagesize($fileTmp);
            if ($imgInfo === FALSE) {
                \Drupal::logger('file_upload')->warning('Invalid image detected for file: @file', ['@file' => $fileTmp]);
                return new JsonResponse(['status' => FALSE, 'message' => 'Invalid image content!']);
            }

            // Re-process image to strip malicious payloads
            $image = imagecreatefromstring(file_get_contents($fileTmp));
            if ($image !== FALSE) {
                imagejpeg($image, $fileTmp, 90);
                imagedestroy($image);
                \Drupal::logger('file_upload')->info('Image sanitized successfully: @file', ['@file' => $fileTmp]);
            }
        } elseif ($mimeType === 'application/pdf') {

            // PDF validation
            $content = file_get_contents($fileTmp);
            \Drupal::logger('file_upload')->debug('PDF first 200 chars: @snippet', [
                '@snippet' => substr($content, 0, 200),
            ]);
            if (preg_match('/\/(JS|JavaScript|AA)/i', $content)) {
                \Drupal::logger('file_upload')->error('Malicious PDF detected for file: @file', ['@file' => $fileTmp]);
                return new JsonResponse(['status' => FALSE, 'message' => 'Malicious PDF detected!']);
            }
            \Drupal::logger('file_upload')->info('PDF passed validation: @file', ['@file' => $fileTmp]);
        }

        $uuidFilename = $this->uuidService->generate() . '.' . $extension;
        if ($fileTypeValid) {
            $cfile = curl_file_create($_FILES['files']['tmp_name']['upload_file'], $_FILES['files']['type']['upload_file'], $uuidFilename);
            $postRequest = [
                UPLOAD_FILE => $cfile,
                'success_action_status' => 200,
            ];
            $globals = $this->globalVariablesService->getGlobalVariables();
            $fileUplPath = $globals['applicationConfig']['config']['fileuploadPath'] ?? NULL;

            if (!$fileUplPath) {
                return new JsonResponse(['error' => 'Upload path not configured in Vault.'], 500);
            }


            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $fileUplPath . 'upload_media_test1.php',
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_FOLLOWLOCATION => TRUE,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $postRequest,
                CURLOPT_SSL_VERIFYHOST => FALSE,
                CURLOPT_SSL_VERIFYPEER => FALSE,
            ]);

            $response = curl_exec($curl);
            $curl_error = curl_error($curl);
            curl_close($curl);

            if ($response === FALSE) {
                return new JsonResponse(['error' => $curl_error], 500);
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                return new JsonResponse(['error' => 'Invalid JSON response'], 500);
            }

            return new JsonResponse([
                'fileName' => $fileUplPath . $uuidFilename,
                'fileTypeId' => $fileTypeVal,
                'fileTypeVal' => $fileTypeType,
            ]);
        }

        return new JsonResponse([
            'message' => 'Selected file not allowed!',
            'status' => FALSE,
        ]);
    }
}
