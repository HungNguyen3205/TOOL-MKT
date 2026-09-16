<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class GoogleDriveImageService
{
    protected $client;
    protected $service;

    public function __construct()
    {
        $this->client = new Client();
        
        $credentialsPath = config('services.google_drive.credentials_path');
        if (!empty($credentialsPath) && !str_starts_with($credentialsPath, '/')) {
            $credentialsPath = base_path($credentialsPath);
        }
        
        if (empty($credentialsPath) || !file_exists($credentialsPath)) {
            Log::warning("Google Drive credentials not found at: {$credentialsPath}");
            // Optional: fallback logic if needed
        } else {
            $this->client->setAuthConfig($credentialsPath);
            $this->client->addScope(Drive::DRIVE_READONLY);
            $this->service = new Drive($this->client);
        }
    }

    /**
     * Extract folder ID from URL
     */
    public function extractFolderId($url)
    {
        if (preg_match('/folders\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Verify folder access and get info
     */
    public function verifyFolderAccess($folderId)
    {
        if (!$this->service) {
            return [
                'success' => false,
                'message' => 'Chưa cấu hình Google Drive credentials'
            ];
        }

        try {
            // Get folder info
            $folder = $this->service->files->get($folderId, ['fields' => 'id, name']);

            // Get total images count
            $query = "'{$folderId}' in parents and trashed=false and (mimeType='image/jpeg' or mimeType='image/png' or mimeType='image/webp')";
            $files = $this->service->files->listFiles([
                'q' => $query,
                'fields' => 'files(id)'
            ]);

            return [
                'success' => true,
                'data' => [
                    'folder_id' => $folder->getId(),
                    'folder_name' => $folder->getName(),
                    'image_count' => count($files->getFiles()),
                    'accessible' => true
                ]
            ];
        } catch (Exception $e) {
            Log::error("Google Drive verify error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Không thể truy cập thư mục. Kiểm tra lại quyền chia sẻ.'
            ];
        }
    }

    /**
     * List all images in a folder to memory cache
     */
    public function listImagesInFolder($folderId)
    {
        if (!$this->service) return [];

        $images = [];
        $pageToken = null;
        $query = "'{$folderId}' in parents and trashed=false and (mimeType='image/jpeg' or mimeType='image/png' or mimeType='image/webp')";

        do {
            try {
                $response = $this->service->files->listFiles([
                    'q' => $query,
                    'fields' => 'nextPageToken, files(id, name, mimeType, size, thumbnailLink)',
                    'pageToken' => $pageToken
                ]);

                foreach ($response->getFiles() as $file) {
                    $nameWithoutExt = pathinfo($file->getName(), PATHINFO_FILENAME);
                    
                    // Build map for easy lookup
                    if (!isset($images[$nameWithoutExt])) {
                        $images[$nameWithoutExt] = [];
                    }
                    if (!isset($images[$file->getName()])) {
                        $images[$file->getName()] = [];
                    }

                    $fileData = [
                        'id' => $file->getId(),
                        'name' => $file->getName(),
                        'mimeType' => $file->getMimeType(),
                        'size' => $file->getSize(),
                        'thumbnailLink' => $file->getThumbnailLink()
                    ];

                    // Map by both full name and name without extension
                    $images[$nameWithoutExt][] = $fileData;
                    // Only add to full name if it's different
                    if ($nameWithoutExt !== $file->getName()) {
                        $images[$file->getName()][] = $fileData;
                    }
                }
                
                $pageToken = $response->getNextPageToken();
            } catch (Exception $e) {
                Log::error("Google Drive list files error: " . $e->getMessage());
                break;
            }
        } while ($pageToken != null);

        return $images;
    }

    /**
     * Find image by key in the pre-loaded image map
     */
    public function findImageByKey($key, $imageMap)
    {
        if (isset($imageMap[$key])) {
            $matchedFiles = $imageMap[$key];
            if (count($matchedFiles) === 1) {
                return [
                    'status' => 'found',
                    'file' => $matchedFiles[0]
                ];
            } elseif (count($matchedFiles) > 1) {
                return [
                    'status' => 'duplicate',
                    'files' => $matchedFiles
                ];
            }
        }

        return [
            'status' => 'not_found'
        ];
    }

    /**
     * Download image from Drive
     */
    public function downloadImage($fileId, $destinationPath)
    {
        if (!$this->service) return false;

        try {
            $response = $this->service->files->get($fileId, ['alt' => 'media']);
            $content = $response->getBody()->getContents();
            return Storage::disk('public')->put($destinationPath, $content);
        } catch (Exception $e) {
            Log::error("Google Drive download error for {$fileId}: " . $e->getMessage());
            return false;
        }
    }
}
