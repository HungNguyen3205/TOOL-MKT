<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'cta' => $this->cta,
            'hashtags' => $this->hashtags ?? [],
            'objective' => $this->objective,
            'tone' => $this->tone,
            'content_length' => $this->content_length,
            'source' => $this->source,
            'status' => $this->status,
            'ai_model' => $this->ai_model,
            'ai_provider' => $this->ai_provider,
            'selected_version' => $this->selected_version,
            'source_input' => $this->source_input,
            'brand_id' => $this->brand_id,
            'brand' => $this->whenLoaded('brand', function() {
                return ['id' => $this->brand->id, 'name' => $this->brand->name];
            }),
            'content_template_id' => $this->content_template_id,
            'last_saved_at' => $this->last_saved_at ? $this->last_saved_at->toIso8601String() : null,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }
}
