<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand_id' => $this->brand_id,
            'name' => $this->name,
            'description' => $this->description,
            'objective' => $this->objective,
            'opening_style' => $this->opening_style,
            'body_structure' => $this->body_structure ?? [],
            'cta_instruction' => $this->cta_instruction,
            'hashtag_instruction' => $this->hashtag_instruction,
            'additional_instruction' => $this->additional_instruction,
            'example_content' => $this->example_content,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'platform' => $this->platform,
            'content_type' => $this->content_type,
            'default_length' => $this->default_length,
            'default_number_of_versions' => $this->default_number_of_versions,
            'required_fields' => $this->required_fields ?? [],
            'usage_count' => $this->usage_count,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }
}
