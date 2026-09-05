<?php

namespace App\Services\ImageGeneration;

interface ImageGenerationProviderInterface
{
    /**
     * Generate an image based on the given input parameters.
     *
     * @param array $input Array of generation parameters (e.g., 'prompt', 'num_steps', etc.)
     * @return array Returns an array with at least a 'success' boolean and 'image_data' / 'error_message'
     */
    public function generate(array $input): array;

    /**
     * Verify the connection and configuration of the provider.
     *
     * @return array Returns an array containing the status and message
     */
    public function verifyConnection(): array;
}
