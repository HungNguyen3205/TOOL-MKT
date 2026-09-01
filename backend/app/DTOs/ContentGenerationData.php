<?php

namespace App\DTOs;

use App\Models\Brand;
use App\Models\ContentTemplate;

class ContentGenerationData
{
    public function __construct(
        public readonly string $topic,
        public readonly string $mainInformation,
        public readonly string $objective,
        public readonly string $tone,
        public readonly string $length,
        public readonly int $numberOfVersions,
        public readonly ?string $targetAudience = null,
        public readonly array $requiredKeywords = [],
        public readonly array $excludedContent = [],
        public readonly ?Brand $brand = null,
        public readonly ?ContentTemplate $template = null
    ) {}

    public static function fromArray(array $data, ?Brand $brand = null, ?ContentTemplate $template = null): self
    {
        // Gộp dữ liệu
        $mergedKeywords = array_merge(
            $data['required_keywords'] ?? [],
            $brand ? ($brand->required_keywords ?? []) : []
        );
        $mergedKeywords = array_values(array_unique(array_filter($mergedKeywords)));

        $mergedExcluded = array_merge(
            $data['excluded_content'] ?? [],
            $brand ? ($brand->prohibited_terms ?? []) : []
        );
        $mergedExcluded = array_values(array_unique(array_filter($mergedExcluded)));

        // Tone & Target Audience ưu tiên form
        $tone = $data['tone'];
        if (!$tone && $brand && $brand->tone) {
            $tone = $brand->tone;
        }

        $targetAudience = $data['target_audience'] ?? null;
        if (!$targetAudience && $brand && $brand->target_audience) {
            $targetAudience = $brand->target_audience;
        }

        return new self(
            topic: $data['topic'],
            mainInformation: $data['main_information'],
            objective: $data['objective'],
            tone: $tone,
            length: $data['length'],
            numberOfVersions: $data['number_of_versions'],
            targetAudience: $targetAudience,
            requiredKeywords: $mergedKeywords,
            excludedContent: $mergedExcluded,
            brand: $brand,
            template: $template
        );
    }
}
