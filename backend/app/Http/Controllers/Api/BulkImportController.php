<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ImportBatch;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\MediaAsset;
use App\Services\Import\BulkImportService;
use App\Services\Import\DocxPostParser;
use App\Services\Import\SpreadsheetPostParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BulkImportController extends Controller
{
    protected $importService;
    protected $docxParser;
    protected $spreadsheetParser;

    public function __construct(
        BulkImportService $importService,
        DocxPostParser $docxParser,
        SpreadsheetPostParser $spreadsheetParser
    ) {
        $this->importService = $importService;
        $this->docxParser = $docxParser;
        $this->spreadsheetParser = $spreadsheetParser;
    }

    public function verifyFolder(Request $request)
    {
        $request->validate([
            'folder_url' => 'required|url',
        ]);

        $driveService = new \App\Services\GoogleDriveImageService();
        $folderId = $driveService->extractFolderId($request->folder_url);

        if (!$folderId) {
            return response()->json([
                'success' => false,
                'message' => 'Link Google Drive không hợp lệ hoặc không có Folder ID.'
            ], 400);
        }

        $result = $driveService->verifyFolderAccess($folderId);

        if ($result['success']) {
            return response()->json($result);
        } else {
            return response()->json($result, 403);
        }
    }

    public function preview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv|max:102400', // 100MB
            'workspace_id' => 'required|integer',
            'brand_id' => 'nullable|integer',
            'facebook_page_id' => 'nullable|string',
            'timezone' => 'nullable|string',
            'google_drive_folder_id' => 'nullable|string',
        ]);

        try {
            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());
            $originalName = $file->getClientOriginalName();
            
            // Create a temporary batch record
            $batch = ImportBatch::create([
                'workspace_id' => $request->workspace_id,
                'brand_id' => $request->brand_id,
                'facebook_page_id' => $request->facebook_page_id,
                'original_filename' => $originalName,
                'file_type' => $extension,
                'timezone' => $request->timezone ?? 'Asia/Ho_Chi_Minh',
                'status' => 'preview',
                'created_by' => auth()->id() ?? 1, // Fallback for dev if auth not enforced
                'options' => $request->except(['file'])
            ]);

            $path = $file->storeAs('temp/imports', 'batch_' . $batch->id . '.' . $extension, 'public');
            $fullPath = Storage::disk('public')->path($path);

            // Parse file
            $parsedData = [];
            if ($extension === 'docx') {
                $parsedData = $this->docxParser->parse($fullPath, $batch->id);
            } elseif (in_array($extension, ['xlsx', 'csv'])) {
                $parsedData = $this->spreadsheetParser->parse($fullPath);
            }

            // Process data with options
            $options = $request->all();
            
            // Fetch Google Drive images if folder is provided
            if (!empty($options['google_drive_folder_id'])) {
                $driveService = new \App\Services\GoogleDriveImageService();
                $imagesMap = $driveService->listImagesInFolder($options['google_drive_folder_id']);
                $options['drive_images_map'] = $imagesMap;
                
                // Remember folder if requested
                if (!empty($options['remember_drive']) && $options['remember_drive'] == '1' && !empty($options['brand_id'])) {
                    \App\Models\Brand::where('id', $options['brand_id'])->update([
                        'google_drive_folder_id' => $options['google_drive_folder_id'],
                        'google_drive_folder_url' => $options['google_drive_folder_url'] ?? null
                    ]);
                }
            }

            $result = $this->importService->processPreview($parsedData, $options);

            // Update batch stats
            $batch->update([
                'total_rows' => $result['summary']['total'],
                'valid_rows' => $result['summary']['valid'],
                'invalid_rows' => $result['summary']['invalid'],
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'batch_id' => $batch->id,
                    'summary' => $result['summary'],
                    'rows' => $result['rows'],
                    'drive_images_map' => $options['drive_images_map'] ?? []
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Import preview failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function previewSheet(Request $request, \App\Services\Import\GoogleSheetParser $sheetParser)
    {
        $request->validate([
            'sheet_url' => 'required|url',
            'workspace_id' => 'required|integer',
        ]);

        try {
            // Create a temporary batch record
            $batch = ImportBatch::create([
                'workspace_id' => $request->workspace_id,
                'brand_id' => $request->brand_id,
                'facebook_page_id' => $request->facebook_page_id,
                'original_filename' => 'Google Sheet Import',
                'file_type' => 'sheet',
                'timezone' => $request->timezone ?? 'Asia/Ho_Chi_Minh',
                'status' => 'preview',
                'created_by' => auth()->id() ?? 1,
                'options' => $request->except(['sheet_url'])
            ]);

            // Parse sheet
            $parsedData = $sheetParser->parseFromUrl($request->sheet_url, $batch->id);

            // Process data with options
            $options = $request->all();
            $result = $this->importService->processPreview($parsedData, $options);

            // Update batch stats
            $batch->update([
                'total_rows' => $result['summary']['total'],
                'valid_rows' => $result['summary']['valid'],
                'invalid_rows' => $result['summary']['invalid'],
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'batch_id' => $batch->id,
                    'summary' => $result['summary'],
                    'rows' => $result['rows']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Google Sheet Import preview failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|exists:import_batches,id',
            'rows' => 'required|array',
            'action_on_duplicate' => 'nullable|string|in:skip,save_draft,update'
        ]);

        $batch = ImportBatch::findOrFail($request->batch_id);
        $rows = $request->rows;
        $actionOnDuplicate = $request->action_on_duplicate ?? 'skip';

        DB::beginTransaction();
        try {
            $importedCount = 0;

            foreach ($rows as $row) {
                // Skip rows explicitly marked as excluded or invalid
                if (isset($row['exclude']) && $row['exclude'] === true) {
                    continue;
                }
                
                if (isset($row['status']) && $row['status'] === 'duplicate' && $actionOnDuplicate === 'skip') {
                    continue;
                }

                // Prepare post data
                $timezone = $batch->timezone;
                $scheduledAt = null;
                $status = Post::STATUS_DRAFT;
                
                // If the user wants to just save draft, we ignore schedule logic
                if ($actionOnDuplicate !== 'save_draft') {
                    if (!empty($row['publish_date']) && !empty($row['publish_time'])) {
                        $scheduledAt = Carbon::parse($row['publish_date'] . ' ' . $row['publish_time'], $timezone)->utc();
                        // Only schedule if future and page/brand exist
                        if ($scheduledAt->isFuture() && !empty($row['facebook_page_id']) && !empty($row['brand_id'])) {
                            $status = Post::STATUS_SCHEDULED;
                        }
                    }
                }

                $postData = [
                    'workspace_id' => $batch->workspace_id,
                    'brand_id' => $row['brand_id'] ?? $batch->brand_id,
                    'facebook_page_id' => $row['facebook_page_id'] ?? $batch->facebook_page_id,
                    'title' => $row['title'],
                    'content' => $row['content'],
                    'cta' => $row['cta'] ?? null,
                    'hashtags' => isset($row['hashtags']) ? (is_array($row['hashtags']) ? $row['hashtags'] : explode(',', $row['hashtags'])) : null,
                    'source' => 'bulk_import',
                    'status' => $status,
                    'scheduled_at' => $scheduledAt,
                    'timezone' => $timezone,
                    'created_by' => auth()->id() ?? 1,
                    'import_batch_id' => $batch->id,
                    'import_row_number' => $row['_row_number'] ?? null,
                    'import_fingerprint' => $row['import_fingerprint'] ?? null,
                ];

                $post = Post::create($postData);

                // Handle Google Drive Image (from image_key mapping)
                if (!empty($row['google_drive_file']) && !empty($row['google_drive_file']['id'])) {
                    $fileId = $row['google_drive_file']['id'];
                    $mimeType = $row['google_drive_file']['mimeType'];
                    $extension = explode('/', $mimeType)[1] ?? 'jpg';
                    $filename = 'drive_' . Str::random(8) . '.' . $extension;
                    $newPath = 'media/' . $post->workspace_id . '/' . $filename;
                    
                    $driveService = new \App\Services\GoogleDriveImageService();
                    if ($driveService->downloadImage($fileId, $newPath)) {
                        $media = MediaAsset::create([
                            'workspace_id' => $post->workspace_id,
                            'brand_id' => $post->brand_id,
                            'original_name' => $filename,
                            'stored_name' => $filename,
                            'path' => $newPath,
                            'mime_type' => $mimeType,
                            'size_bytes' => Storage::disk('public')->exists($newPath) ? Storage::disk('public')->size($newPath) : 0,
                            'checksum' => Storage::disk('public')->exists($newPath) ? md5_file(Storage::disk('public')->path($newPath)) : '',
                            'disk' => 'public',
                            'type' => 'image',
                            'status' => 'ready',
                            'uploaded_by' => auth()->id() ?? 1
                        ]);

                        PostMedia::create([
                            'post_id' => $post->id,
                            'media_asset_id' => $media->id,
                            'order' => 0
                        ]);
                        
                        // Save import_image_key for reference
                        if (!empty($row['image_key'])) {
                            $post->update(['import_image_key' => $row['image_key']]);
                        }
                    }
                }

                // Handle regular images (if any)
                if (!empty($row['images']) && is_array($row['images'])) {
                    foreach ($row['images'] as $imagePath) {
                        // If it's a relative path from temp storage (Docx)
                        if (str_starts_with($imagePath, 'temp/imports/')) {
                            $newPath = 'media/' . $post->workspace_id . '/' . basename($imagePath);
                            if (Storage::disk('public')->exists($imagePath)) {
                                Storage::disk('public')->copy($imagePath, $newPath);
                                
                                // Create Media Asset
                                $media = MediaAsset::create([
                                    'workspace_id' => $post->workspace_id,
                                    'brand_id' => $post->brand_id,
                                    'original_name' => basename($newPath),
                                    'stored_name' => basename($newPath),
                                    'path' => $newPath,
                                    'mime_type' => Storage::disk('public')->mimeType($newPath),
                                    'size_bytes' => Storage::disk('public')->size($newPath),
                                    'checksum' => md5_file(Storage::disk('public')->path($newPath)),
                                    'disk' => 'public',
                                    'type' => 'image',
                                    'status' => 'ready',
                                    'uploaded_by' => auth()->id() ?? 1
                                ]);

                                // Link to Post
                                PostMedia::create([
                                    'post_id' => $post->id,
                                    'media_asset_id' => $media->id,
                                    'order' => 0
                                ]);
                            }
                        }
                        // Handle external URL images if from spreadsheet (basic support)
                        elseif (filter_var($imagePath, FILTER_VALIDATE_URL)) {
                            // Convert Google Drive view links to download links
                            $downloadUrl = $imagePath;
                            if (preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $imagePath, $matches) || preg_match('/id=([a-zA-Z0-9-_]+)/', $imagePath, $matches)) {
                                $fileId = $matches[1];
                                $downloadUrl = "https://drive.google.com/uc?export=download&id={$fileId}";
                            }

                            // Download and save
                            try {
                                $contents = file_get_contents($downloadUrl);
                                $filename = 'download_' . Str::random(8) . '.jpg';
                                $newPath = 'media/' . $post->workspace_id . '/' . $filename;
                                Storage::disk('public')->put($newPath, $contents);
                                
                                $media = MediaAsset::create([
                                    'workspace_id' => $post->workspace_id,
                                    'brand_id' => $post->brand_id,
                                    'original_name' => basename($newPath),
                                    'stored_name' => basename($newPath),
                                    'path' => $newPath,
                                    'mime_type' => 'image/jpeg',
                                    'size_bytes' => Storage::disk('public')->exists($newPath) ? Storage::disk('public')->size($newPath) : 0,
                                    'checksum' => Storage::disk('public')->exists($newPath) ? md5_file(Storage::disk('public')->path($newPath)) : '',
                                    'disk' => 'public',
                                    'type' => 'image',
                                    'status' => 'ready',
                                    'uploaded_by' => auth()->id() ?? 1
                                ]);

                                PostMedia::create([
                                    'post_id' => $post->id,
                                    'media_asset_id' => $media->id,
                                    'order' => 0
                                ]);
                            } catch (\Exception $e) {
                                Log::warning("Failed to download external image: $imagePath");
                            }
                        }
                    }
                }

                $importedCount++;
            }

            $batch->update([
                'status' => 'completed',
                'imported_rows' => $importedCount
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Đã import và lên lịch thành công $importedCount bài viết.",
                'data' => [
                    'imported' => $importedCount
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Import confirm failed: ' . $e->getMessage());
            
            $batch->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lưu dữ liệu: ' . $e->getMessage()
            ], 500);
        }
    }
}
