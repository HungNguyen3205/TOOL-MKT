<?php

namespace App\Services\Import;

use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BulkImportService
{
    public function processPreview(array $parsedData, array $options): array
    {
        $processedRows = [];
        $summary = [
            'total' => count($parsedData),
            'valid' => 0,
            'invalid' => 0,
            'duplicates' => 0
        ];

        $timezone = $options['timezone'] ?? 'Asia/Ho_Chi_Minh';
        $scheduleMode = $options['schedule_mode'] ?? 'excel';
        
        $currentScheduleDate = null;
        $defaultTimes = [];
        $frequency = 1;
        $timeIndex = 0;

        if ($scheduleMode === 'tool') {
            $startDate = $options['start_date'] ?? Carbon::now($timezone)->format('Y-m-d');
            $currentScheduleDate = Carbon::parse($startDate, $timezone);
            $defaultTimes = $options['times'] ?? ['08:00'];
            $frequency = (int)($options['frequency'] ?? '1');
            
            if ($currentScheduleDate->isPast() && !$currentScheduleDate->isToday()) {
                // If it's today, we might schedule for later today, so it's fine. If strictly past day, warn or adjust.
                // We'll let the precise time validation catch it.
            }
        }

        foreach ($parsedData as $row) {
            $processedRow = $this->standardizeRow($row, $options);
            
            // Check required fields
            $errors = [];
            if (empty($processedRow['title']) || empty($processedRow['content'])) {
                $errors[] = 'Thiếu tiêu đề hoặc nội dung';
            }

            if ($scheduleMode === 'tool') {
                $processedRow['publish_date'] = $currentScheduleDate->format('Y-m-d');
                $processedRow['publish_time'] = $defaultTimes[$timeIndex];
                
                // Increment time/date
                $timeIndex++;
                if ($timeIndex >= count($defaultTimes) || $timeIndex >= $frequency) {
                    $timeIndex = 0;
                    $currentScheduleDate->addDay();
                }
            } else {
                // Excel mode
                if (empty($processedRow['publish_date']) || empty($processedRow['publish_time'])) {
                    $errors[] = 'Thiếu ngày/giờ đăng';
                }
            }

            // Validate schedule
            if (!empty($processedRow['publish_date']) && !empty($processedRow['publish_time'])) {
                try {
                    // Try parsing common formats
                    $dateStr = str_replace('/', '-', $processedRow['publish_date']);
                    if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $dateStr)) {
                        // dd-mm-yyyy to yyyy-mm-dd
                        $dateStr = Carbon::createFromFormat('d-m-Y', $dateStr)->format('Y-m-d');
                    }
                    $processedRow['publish_date'] = $dateStr;

                    $scheduledAt = Carbon::parse($processedRow['publish_date'] . ' ' . $processedRow['publish_time'], $timezone);
                    $processedRow['scheduled_at'] = $scheduledAt->toIso8601String();
                    
                    if ($scheduledAt->isPast()) {
                        $errors[] = 'Thời gian đăng trong quá khứ';
                    }
                } catch (\Exception $e) {
                    $errors[] = 'Định dạng ngày/giờ không hợp lệ';
                }
            }

            // Google Drive Image Mapping
            $imageKey = $processedRow['image_key'] ?? null;
            if (empty($imageKey) && !empty($processedRow['images']) && is_array($processedRow['images'])) {
                // Fallback: If user put the image name in the "Ảnh" column instead of "Mã ảnh"
                $imageKey = $processedRow['images'][0];
                $processedRow['image_key'] = $imageKey;
            }
            if (!empty($imageKey) && isset($options['drive_images_map'])) {
                $driveService = new \App\Services\GoogleDriveImageService();
                $driveResult = $driveService->findImageByKey($imageKey, $options['drive_images_map']);
                
                if ($driveResult['status'] === 'found') {
                    $processedRow['google_drive_file'] = $driveResult['file'];
                } elseif ($driveResult['status'] === 'duplicate') {
                    $processedRow['google_drive_error'] = 'duplicate';
                    $errors[] = 'Trùng tên ảnh trên Drive (' . count($driveResult['files']) . ' files)';
                } else {
                    $processedRow['google_drive_error'] = 'not_found';
                    $errors[] = 'Không tìm thấy ảnh trên Drive';
                }
            }

            // Fingerprint & Duplicate check
            $fingerprint = $this->generateFingerprint($processedRow);
            $processedRow['import_fingerprint'] = $fingerprint;
            
            // Strictly check page_id and time duplicate
            $pageId = $processedRow['facebook_page_id'] ?? null;
            $schedAt = $processedRow['scheduled_at'] ?? null;
            if ($pageId && $schedAt) {
                $isExactDuplicate = Post::where('facebook_page_id', $pageId)
                    ->where('scheduled_at', Carbon::parse($schedAt)->utc())
                    ->exists();
                if ($isExactDuplicate) {
                    $errors[] = 'Trùng lịch đăng trên Page này';
                }
            }

            $isDuplicate = Post::where('import_fingerprint', $fingerprint)->exists();
            if ($isDuplicate) {
                $processedRow['is_duplicate'] = true;
                $errors[] = 'Trùng lặp bài viết đã tồn tại';
            } else {
                $processedRow['is_duplicate'] = false;
            }

            $processedRow['errors'] = $errors;
            $processedRow['is_valid'] = empty($errors);
            $processedRow['status'] = $processedRow['is_valid'] ? 'valid' : ($processedRow['is_duplicate'] ? 'duplicate' : 'invalid');

            if ($processedRow['status'] === 'valid') {
                $summary['valid']++;
            } elseif ($processedRow['status'] === 'duplicate') {
                $summary['duplicates']++;
                $summary['invalid']++;
            } else {
                $summary['invalid']++;
            }

            $processedRows[] = $processedRow;
        }

        return [
            'summary' => $summary,
            'rows' => $processedRows
        ];
    }

    protected function standardizeRow(array $row, array $options): array
    {
        return [
            '_row_number' => $row['_row_number'] ?? null,
            'title' => $row['title'] ?? '',
            'content' => $row['content'] ?? '',
            'cta' => $row['cta'] ?? '',
            'hashtags' => $row['hashtags'] ?? [],
            'images' => isset($row['images']) ? $row['images'] : (isset($row['image']) && $row['image'] ? [$row['image']] : []),
            'image_key' => $row['image_key'] ?? null,
            'publish_date' => $row['publish_date'] ?? null,
            'publish_time' => $row['publish_time'] ?? null,
            'facebook_page_id' => $row['facebook_page_id'] ?? $options['facebook_page_id'] ?? null,
            'brand_id' => $row['brand_id'] ?? $options['brand_id'] ?? null,
            'workspace_id' => $options['workspace_id'] ?? null,
        ];
    }

    protected function generateFingerprint(array $row): string
    {
        $data = [
            'brand_id' => $row['brand_id'] ?? '',
            'page_id' => $row['facebook_page_id'] ?? '',
            'title' => trim(mb_strtolower($row['title'] ?? '')),
            'content' => md5(trim(mb_strtolower($row['content'] ?? ''))),
            'scheduled_at' => $row['scheduled_at'] ?? ''
        ];
        
        return hash('sha256', implode('|', $data));
    }
}
