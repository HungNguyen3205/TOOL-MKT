<?php

namespace App\DTOs;

class ContentGenerationResult
{
    public function __construct(
        public readonly array $versions,
        public readonly array $metadata
    ) {}
}
