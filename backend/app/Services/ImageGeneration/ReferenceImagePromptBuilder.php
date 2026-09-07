<?php

namespace App\Services\ImageGeneration;

use App\Models\Post;

class ReferenceImagePromptBuilder
{
    /**
     * Build a comprehensive prompt for image generation.
     *
     * @param Post $post
     * @param array $referenceAssets
     * @return string
     */
    public function buildPrompt(Post $post, array $referenceAssets = []): string
    {
        $prompt = "Create a professional marketing image based on the following specifications:\n\n";

        $prompt .= "--- POST CONTENT ---\n";
        $prompt .= "Title: " . ($post->title ?? '') . "\n";
        $prompt .= "Content: " . ($post->content ?? '') . "\n";
        $prompt .= "CTA: " . ($post->cta ?? '') . "\n\n";

        $prompt .= "--- BASE IMAGE PROMPT ---\n";
        $prompt .= ($post->image_prompt ?? '') . "\n\n";

        $visualBrief = $post->visual_brief ?? [];
        if (!empty($visualBrief)) {
            $prompt .= "--- VISUAL BRIEF ---\n";
            $prompt .= "1. Main Message: " . ($visualBrief['main_message'] ?? '') . "\n";
            $prompt .= "2. Target Audience: " . ($visualBrief['target_audience'] ?? '') . "\n";
            $prompt .= "3. Main Subject: " . ($visualBrief['visual_subject'] ?? '') . "\n";
            $prompt .= "4. Environment/Setting: " . ($visualBrief['environment'] ?? '') . "\n";
            $prompt .= "5. Pain Point to Visualize: " . ($visualBrief['pain_point_visual'] ?? '') . "\n";
            $prompt .= "6. Solution to Visualize: " . ($visualBrief['solution_visual'] ?? '') . "\n";
            $prompt .= "7. Color Direction: " . ($visualBrief['color_direction'] ?? '') . "\n";
            $prompt .= "8. Forbidden Elements: " . implode(', ', $visualBrief['forbidden_elements'] ?? []) . "\n";
        }

        if ($post->brand) {
            $prompt .= "\n--- BRAND GUIDELINES ---\n";
            $prompt .= "Brand Name: " . $post->brand->name . "\n";
            $prompt .= "Industry: " . $post->brand->industry . "\n";
            $prompt .= "Brand Tone: " . $post->brand->tone . "\n";
            $prompt .= "Primary Colors: " . implode(', ', $post->brand->brand_colors ?? []) . "\n";
        }

        if (!empty($referenceAssets)) {
            $prompt .= "\n--- REFERENCE ASSETS PROVIDED ---\n";
            $prompt .= "You have been provided with " . count($referenceAssets) . " reference image(s). ";
            $prompt .= "If a software screenshot is provided, use it as the primary UI layer and only generate the surrounding context, people, or background. ";
            $prompt .= "DO NOT generate fake text, fake UI, fake QR codes, or fake logos.\n";
            foreach ($referenceAssets as $idx => $asset) {
                $role = $asset['role'] ?? 'reference';
                $prompt .= "- Asset " . ($idx + 1) . " (Role: {$role})\n";
            }
        }

        $prompt .= "\n--- FINAL INSTRUCTIONS ---\n";
        $prompt .= "Focus strictly on the visual aspects. Produce a highly realistic and professional image. Do not include any textual overlay unless explicitly requested.";

        return $prompt;
    }
}
