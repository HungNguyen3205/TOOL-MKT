<?php

namespace App\Services\VideoGeneration;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class VideoBrandingService
{
    /**
     * Overlay a logo onto a video using FFmpeg.
     *
     * @param string $sourceVideoPath Path relative to storage/app/public
     * @param string $logoPath Path relative to storage/app/public
     * @param string $outputVideoPath Path relative to storage/app/public
     * @return bool
     */
    public function addLogoToVideo(string $sourceVideoPath, string $logoPath, string $outputVideoPath): bool
    {
        $absSource = storage_path('app/public/' . $sourceVideoPath);
        $absLogo = storage_path('app/public/' . $logoPath);
        $absOutput = storage_path('app/public/' . $outputVideoPath);

        if (!file_exists($absSource)) {
            Log::error("VideoBrandingService: Source video not found at {$absSource}");
            return false;
        }

        if (!file_exists($absLogo)) {
            Log::error("VideoBrandingService: Logo not found at {$absLogo}");
            return false;
        }

        // FFmpeg command to overlay logo at top-right corner with some padding (e.g., 20px)
        // Logo is scaled to a reasonable size relative to the video, for example 150px width.
        $ffmpegCommand = [
            'ffmpeg',
            '-y', // Overwrite output file if it exists
            '-i', $absSource,
            '-i', $absLogo,
            '-filter_complex', '[1:v]scale=150:-1[logo];[0:v][logo]overlay=W-w-20:20',
            '-c:a', 'copy', // Copy audio as is
            $absOutput
        ];

        try {
            $process = new Process($ffmpegCommand);
            $process->setTimeout(600); // 10 minutes timeout
            $process->run();

            if (!$process->isSuccessful()) {
                Log::error("VideoBrandingService FFmpeg Error: " . $process->getErrorOutput());
                return false;
            }

            return file_exists($absOutput);
        } catch (\Exception $e) {
            Log::error("VideoBrandingService Exception: " . $e->getMessage());
            return false;
        }
    }
}
