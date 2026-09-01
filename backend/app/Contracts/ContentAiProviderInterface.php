<?php

namespace App\Contracts;

use App\DTOs\ContentGenerationData;
use App\DTOs\ContentGenerationResult;

interface ContentAiProviderInterface
{
    public function generate(ContentGenerationData $data): ContentGenerationResult;
}
