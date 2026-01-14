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
    public function uploadFile(Request $request): JsonResponse
    {
        define('UPLOAD_FILE', 'uploadedfile1');

        $fileInfo = $this->getUploadedFileInfo();
        if (!$fileInfo) {
            return $this->errorResponse('No file uploaded.', 400);
        }

        [$tmpFile, $originalName, $mimeType] = $fileInfo;

        if (!$this->isMimeAllowed($mimeType)) {
            return $this->errorResponse('File content not allowed!');
        }

        if ($this->hasMultipleExtensions($originalName)) {
            return $this->errorResponse('Multiple file extensions not allowed');
        }

        $fileType = $this->detectFileType($originalName);
        if (!$fileType) {
            return $this->errorResponse('Selected file not allowed!');
        }

        if (!$this->validateFileContent($tmpFile)) {
            return $this->errorResponse('Malicious file detected!');
        }

        return $this->uploadToRemote($tmpFile, $originalName, $fileType);
    }

    private function getUploadedFileInfo(): ?array
    {
        if (empty($_FILES['files']['tmp_name']['upload_file'])) {
            return NULL;
        }

        return [
            $_FILES['files']['tmp_name']['upload_file'],
            $_FILES['files']['name']['upload_file'],
            mime_content_type($_FILES['files']['tmp_name']['upload_file']),
        ];
    }

    private function isMimeAllowed(string $mime): bool
    {
        return in_array($mime, [
            'image/jpeg',
            'image/png',
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'video/mp4',
        ], TRUE);
    }

    private function hasMultipleExtensions(string $filename): bool
    {
        return substr_count($filename, '.') > 1;
    }

    private function detectFileType(string $filename): ?array
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match (TRUE) {
            in_array($ext, ['jpg', 'jpeg', 'png']) =>
            ['id' => 2, 'type' => 'image'],
            in_array($ext, ['pdf', 'doc', 'docx', 'mp3', 'xlsx']) =>
            ['id' => 4, 'type' => 'file'],
            $ext === 'mp4' =>
            ['id' => 1, 'type' => 'video'],
            default => NULL,
        };
    }

    private function validateFileContent(string $tmpFile): bool
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpFile);

        if (str_starts_with($mimeType, 'image/')) {
            return $this->validateImage($tmpFile);
        }

        if ($mimeType === 'application/pdf') {
            return $this->validatePdf($tmpFile);
        }

        return TRUE;
    }

    private function validateImage(string $file): bool
    {
        if (!getimagesize($file)) {
            return FALSE;
        }

        $image = imagecreatefromstring(file_get_contents($file));
        if ($image) {
            imagejpeg($image, $file, 90);
            imagedestroy($image);
        }

        return TRUE;
    }

    private function validatePdf(string $file): bool
    {
        $content = file_get_contents($file);

        return !preg_match('/\/(JS|JavaScript|AA)/i', $content);
    }

    private function uploadToRemote(string $tmpFile, string $originalName, array $fileType): JsonResponse
    {
        $uuidFilename = $this->uuidService->generate() . '.' . pathinfo($originalName, PATHINFO_EXTENSION);

        $globals = $this->globalVariablesService->getGlobalVariables();
        $fileUplPath = $globals['applicationConfig']['config']['fileuploadPath'] ?? NULL;

        if (!$fileUplPath) {
            return $this->errorResponse('Upload path not configured in Vault.', 500);
        }

        $cfile = curl_file_create($tmpFile, mime_content_type($tmpFile), $uuidFilename);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $fileUplPath . 'upload_media_test1.php',
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => [
                UPLOAD_FILE => $cfile,
                'success_action_status' => 200,
            ],
            CURLOPT_SSL_VERIFYHOST => FALSE,
            CURLOPT_SSL_VERIFYPEER => FALSE,
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        if ($response === FALSE || json_last_error() !== JSON_ERROR_NONE) {
            return $this->errorResponse('Upload failed.', 500);
        }

        return new JsonResponse([
            'fileName' => $fileUplPath . $uuidFilename,
            'fileTypeId' => $fileType['id'],
            'fileTypeVal' => $fileType['type'],
        ]);
    }

    private function errorResponse(string $message, int $status = 200): JsonResponse
    {
        return new JsonResponse([
            'status' => FALSE,
            'message' => $message,
        ], $status);
    }
}
