<?php

namespace App\Services\TextGeneration;

interface TextGenerationProviderInterface
{
    /**
     * Generate text content based on the given prompt and parameters.
     *
     * @param string $prompt The main prompt to send to the AI
     * @param array $parameters Optional parameters (e.g. max tokens, variants)
     * @return array Returns an array with 'success', 'data' (JSON parsed), 'error_code', 'error_message'
     */
    public function generate(string $prompt, array $parameters = []): array;
}
