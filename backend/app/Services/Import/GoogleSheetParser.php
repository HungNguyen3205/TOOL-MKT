<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GoogleSheetParser extends SpreadsheetPostParser
{
    public function parseFromUrl(string $url, int $batchId): array
    {
        // Convert to export URL
        $csvUrl = $this->getExportUrl($url);
        if (!$csvUrl) {
            throw new \Exception('Link Google Sheet không hợp lệ. Vui lòng copy link ở thanh địa chỉ hoặc nút Share.');
        }

        try {
            $response = Http::timeout(30)->get($csvUrl);
            if (!$response->successful()) {
                throw new \Exception('Không thể tải file Google Sheet. Vui lòng đảm bảo File được Share "Anyone with the link".');
            }

            $csvData = $response->body();
            
            // Parse CSV data
            $lines = explode("\n", $csvData);
            $rows = array_map('str_getcsv', $lines);
            
            // Remove empty lines
            $rows = array_filter($rows, function($row) {
                return !empty(array_filter($row));
            });
            
            // Re-index array
            $rows = array_values($rows);

            if (empty($rows)) {
                return [];
            }

            $headers = array_shift($rows);
            $columnMap = $this->mapHeaders($headers);
            
            $parsedData = [];
            
            foreach ($rows as $index => $row) {
                // Ensure row has same elements as headers
                $row = array_pad($row, count($headers), '');
                
                $rowData = $this->extractRowData($row, $columnMap);
                $rowData['_row_number'] = $index + 2;
                
                // Special handling for Drive Images
                if (!empty($rowData['image'])) {
                    $driveImages = $this->extractAndDownloadDriveImages($rowData['image'], $batchId, $index + 2);
                    $rowData['images'] = $driveImages;
                } else {
                    $rowData['images'] = [];
                }
                
                $parsedData[] = $rowData;
            }

            return $parsedData;

        } catch (\Exception $e) {
            Log::error('Google Sheet parsing failed: ' . $e->getMessage());
            throw new \Exception($e->getMessage() ?: 'Không thể xử lý Google Sheet. Vui lòng thử lại.');
        }
    }

    protected function getExportUrl(string $url): ?string
    {
        // Example: https://docs.google.com/spreadsheets/d/1X2Y3Z/edit#gid=0
        if (preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
            $id = $matches[1];
            $gid = 0;
            if (preg_match('/gid=([0-9]+)/', $url, $gidMatches)) {
                $gid = $gidMatches[1];
            }
            return "https://docs.google.com/spreadsheets/d/{$id}/export?format=csv&gid={$gid}";
        }
        return null;
    }

    protected function extractAndDownloadDriveImages(string $imageString, int $batchId, int $rowNum): array
    {
        $urls = preg_split('/[\s,;]+/', $imageString);
        $downloadedPaths = [];

        foreach ($urls as $url) {
            $url = trim($url);
            if (empty($url)) continue;

            $fileId = $this->getDriveFileId($url);
            if ($fileId) {
                $path = $this->downloadDriveImage($fileId, $batchId, $rowNum);
                if ($path) {
                    $downloadedPaths[] = $path;
                }
            } else {
                // Not a drive link, keep as is
                $downloadedPaths[] = $url;
            }
        }

        return $downloadedPaths;
    }

    protected function getDriveFileId(string $url): ?string
    {
        if (preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
            return $matches[1];
        }
        if (preg_match('/id=([a-zA-Z0-9-_]+)/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    protected function downloadDriveImage(string $fileId, int $batchId, int $rowNum): ?string
    {
        try {
            $downloadUrl = "https://drive.google.com/uc?export=download&id={$fileId}";
            
            // Stream the download to avoid memory issues for large images
            $response = Http::timeout(30)->get($downloadUrl);
            
            if ($response->successful()) {
                // Some google drive links redirect to a warning page for large files, but for standard images it's fine.
                $body = $response->body();
                
                // Basic check if it's actually an image (or if Google returned HTML warning)
                if (str_starts_with($body, '<!DOCTYPE html>')) {
                    Log::warning("Google Drive returned HTML instead of image for file ID: $fileId");
                    return null;
                }

                $filename = 'batch_' . $batchId . '_row_' . $rowNum . '_' . Str::random(6) . '.jpg';
                $path = 'temp/imports/' . $filename;
                
                Storage::disk('public')->put($path, $body);
                return $path;
            }
        } catch (\Exception $e) {
            Log::error("Failed to download drive image $fileId: " . $e->getMessage());
        }
        
        return null;
    }
}
