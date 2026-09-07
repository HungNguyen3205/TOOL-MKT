<?php

namespace App\Jobs;

use App\Models\Post;
use App\Models\MediaAsset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\VideoGeneration\VideoGenerationProviderInterface;
use App\Services\ImageGeneration\ReferenceImagePromptBuilder;
use Exception;

class GenerateVideoFinalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800;
    public $tries = 1;
    public $failOnTimeout = true;

    protected $post;
    protected $mediaAsset;
    protected $referenceAssets;

    public function __construct(Post $post, MediaAsset $mediaAsset, array $referenceAssets = [])
    {
        $this->post = $post;
        $this->mediaAsset = $mediaAsset;
        $this->referenceAssets = $referenceAssets;
        $this->onQueue('video-generation');
    }

    public function handle(VideoGenerationProviderInterface $provider, ReferenceImagePromptBuilder $promptBuilder): void
    {
        try {
            $this->mediaAsset->update(['status' => MediaAsset::STATUS_VIDEO_FINAL_PROCESSING]);

            // Build Prompt - reuse same parameters
            $prompt = $promptBuilder->buildPrompt($this->post, $this->referenceAssets);
            
            $referenceImage = null;
            if (!empty($this->referenceAssets) && isset($this->referenceAssets[0]['path'])) {
                $referenceImage = storage_path('app/public/' . $this->referenceAssets[0]['path']);
            }

            $options = [];
            $metadata = $this->mediaAsset->metadata ?? [];
            if (!empty($metadata['provider_cookie'])) {
                $options['cookie'] = $metadata['provider_cookie'];
            }

            // 1. Send Request for FINAL video
            $response = $provider->generateFinal($prompt, $referenceImage, $options);

            if (!$response['success']) {
                $this->failWith($response['error_code'] ?? 'VIDEO_PROCESSING_FAILED', $response['error_message'] ?? 'Failed to send final request');
                return;
            }

            $operationId = $response['operation_id'];
            $metadata['operation_id'] = $operationId;
            $metadata['stage'] = 'final';
            $this->mediaAsset->update(['metadata' => $metadata]);

            // 2. Poll Status
            $pollInterval = (int) env('VIDEO_POLL_INTERVAL_SECONDS', 10);
            $maxWait = $this->timeout - 60;
            $elapsed = 0;

            while ($elapsed < $maxWait) {
                sleep($pollInterval);
                $elapsed += $pollInterval;

                $statusResponse = $provider->checkStatus($operationId);

                if (!$statusResponse['success'] || $statusResponse['status'] === 'failed') {
                    $this->failWith('VIDEO_PROCESSING_FAILED', $statusResponse['error_message'] ?? 'Polling failed');
                    return;
                }

                if ($statusResponse['status'] === 'completed') {
                    // 3. Download Video
                    $uuid = \Illuminate\Support\Str::uuid();
                    $destinationPath = "generated-videos/{$this->post->workspace_id}/{$this->post->id}/{$uuid}_final.mp4";
                    
                    $downloaded = $provider->downloadVideo($statusResponse['video_url'], $destinationPath);

                    if (!$downloaded) {
                        $this->failWith('VIDEO_DOWNLOAD_FAILED', 'Failed to download the finished final video');
                        return;
                    }

                    // Update media asset
                    $this->mediaAsset->update([
                        'status' => MediaAsset::STATUS_VIDEO_FINAL_READY,
                        'path' => $destinationPath,
                        'mime_type' => 'video/mp4',
                        'processed_at' => now()
                    ]);

                    // Dispatch branding job
                    ProcessVideoBrandingJob::dispatch($this->post, $this->mediaAsset);

                    return;
                }
            }

            $this->failWith('VIDEO_OPERATION_TIMEOUT', 'Polling timed out');

        } catch (Exception $e) {
            $this->failWith('VIDEO_PROCESSING_FAILED', $e->getMessage());
        }
    }

    protected function failWith(string $errorCode, string $errorMessage)
    {
        Log::error("GenerateVideoFinalJob Failed: [{$errorCode}] {$errorMessage}");
        $this->mediaAsset->update([
            'status' => MediaAsset::STATUS_VIDEO_FINAL_FAILED,
            'error_code' => $errorCode,
            'error_message' => $errorMessage
        ]);
        $this->fail(new Exception("[{$errorCode}] {$errorMessage}"));
    }
}
