<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'industry' => $this->industry,
            'description' => $this->description,
            'products_services' => $this->products_services,
            'target_audience' => $this->target_audience,
            'tone' => $this->tone,
            'slogan' => $this->slogan,
            'default_cta' => $this->default_cta,
            'default_hashtags' => $this->default_hashtags ?? [],
            'required_keywords' => $this->required_keywords ?? [],
            'prohibited_terms' => $this->prohibited_terms ?? [],
            'writing_rules' => $this->writing_rules ?? [],
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'brand_type' => $this->brand_type,
            'website' => $this->website,
            'hotline' => $this->hotline,
            'email' => $this->email,
            'address' => $this->address,
            'service_areas' => $this->service_areas ?? [],
            'positioning' => $this->positioning,
            'unique_value_proposition' => $this->unique_value_proposition,
            'brand_story' => $this->brand_story,
            'brand_personality' => $this->brand_personality,
            'competitive_advantages' => $this->competitive_advantages ?? [],
            'customer_pain_points' => $this->customer_pain_points ?? [],
            'customer_desires' => $this->customer_desires ?? [],
            'customer_objections' => $this->customer_objections ?? [],
            'default_language' => $this->default_language,
            'emoji_limit' => $this->emoji_limit,
            'preferred_addressing' => $this->preferred_addressing,
            'platform_rules' => $this->platform_rules ?? [],
            'profile_completeness' => $this->profile_completeness,
            'templates_count' => $this->whenCounted('templates'),
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }
}
