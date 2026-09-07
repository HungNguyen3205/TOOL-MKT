<?php

namespace App\Services\VideoGeneration;

interface VideoGenerationProviderInterface
{
    /**
     * Generate a draft video (lower quality/duration/cost)
     * 
     * @param string $prompt
     * @param string|null $referenceImage
     * @param array $options
     * @return array
     */
    public function generateDraft(string $prompt, ?string $referenceImage = null, array $options = []): array;

    /**
     * Generate a final high-quality video
     * 
     * @param string $prompt
     * @param string|null $referenceImage
     * @param array $options
     * @return array
     */
    public function generateFinal(string $prompt, ?string $referenceImage = null, array $options = []): array;

    /**
     * Check the status of a long-running video generation operation
     * 
     * @param string $operationId
     * @return array
     */
    public function checkStatus(string $operationId): array;

    /**
     * Download the finished video from the provider
     * 
     * @param string $videoUrl
     * @param string $destinationPath
     * @return bool
     */
    public function downloadVideo(string $videoUrl, string $destinationPath): bool;
}
