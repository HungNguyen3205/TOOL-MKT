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
        public readonly ?string $tone = null,
        public readonly ?string $length = null,
        public readonly int $numberOfVersions,
        public readonly ?string $targetAudience = null,
        public readonly array $requiredKeywords = [],
        public readonly array $excludedContent = [],
        public readonly ?string $ctaInstruction = null,
        public readonly ?string $hashtagInstruction = null,
        public readonly ?Brand $brand = null,
        public readonly ?ContentTemplate $template = null,
        public readonly array $knowledgeItems = [],
        public readonly bool $useContactInfo = false
    ) {}

    public static function fromArray(array $data, ?Brand $brand = null, ?ContentTemplate $template = null): self
    {
        // Gộp dữ liệu
        $requiredKeywords = $data['required_keywords'] ?? null;
        if ($requiredKeywords === null) {
            $requiredKeywords = $brand ? ($brand->required_keywords ?? []) : [];
        }

        $excludedContent = $data['excluded_content'] ?? null;
        if ($excludedContent === null) {
            $excludedContent = $brand ? ($brand->prohibited_terms ?? []) : [];
        }

        $tone = $data['tone'] ?? null;
        if (!$tone && $brand && $brand->tone) {
            $tone = $brand->tone;
        }

        $targetAudience = $data['target_audience'] ?? null;
        if (!$targetAudience && $brand && $brand->target_audience) {
            $targetAudience = $brand->target_audience;
        }

        $ctaInstruction = $data['cta_instruction'] ?? null;
        if (!$ctaInstruction) {
            $ctaInstruction = $template ? $template->cta_instruction : ($brand ? $brand->default_cta : null);
        }

        $hashtagInstruction = $data['hashtag_instruction'] ?? null;
        if (!$hashtagInstruction) {
            $hashtagInstruction = $template ? $template->hashtag_instruction : ($brand ? ($brand->default_hashtags ? implode(' ', $brand->default_hashtags) : null) : null);
        }

        return new self(
            topic: $data['topic'],
            mainInformation: $data['main_information'],
            objective: $data['objective'],
            tone: $tone,
            length: $data['length'],
            numberOfVersions: $data['number_of_versions'],
            targetAudience: $targetAudience,
            requiredKeywords: is_array($requiredKeywords) ? $requiredKeywords : [],
            excludedContent: is_array($excludedContent) ? $excludedContent : [],
            ctaInstruction: $ctaInstruction,
            hashtagInstruction: $hashtagInstruction,
            brand: $brand,
            template: $template,
            knowledgeItems: $data['knowledge_items'] ?? [],
            useContactInfo: $data['use_contact_info'] ?? false
        );
    }
}
