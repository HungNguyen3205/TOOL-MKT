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
            'quality_score' => $this->quality_score,
            'quality_status' => $this->quality_status,
            'quality_result' => $this->quality_result,
            'content_version' => $this->content_version,
            'review_note' => $this->review_note,
            'last_saved_at' => $this->last_saved_at ? $this->last_saved_at->toIso8601String() : null,
            'quality_checked_at' => $this->quality_checked_at ? $this->quality_checked_at->toIso8601String() : null,
            'submitted_for_review_at' => $this->submitted_for_review_at ? $this->submitted_for_review_at->toIso8601String() : null,
            'approved_at' => $this->approved_at ? $this->approved_at->toIso8601String() : null,
            'ready_at' => $this->ready_at ? $this->ready_at->toIso8601String() : null,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
            'media' => $this->whenLoaded('media', function () {
                return $this->media->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'type' => $media->type,
                        'status' => $media->status,
                        'url' => url(\Illuminate\Support\Facades\Storage::disk($media->disk)->url($media->path)),
                        'role' => $media->pivot?->role,
                        'position' => $media->pivot?->position,
                        'width' => $media->width,
                        'height' => $media->height,
                    ];
                })->values();
            }),
            'image_url' => $this->whenLoaded('media', function () {
                $image = $this->media
                    ->where('type', 'image')
                    ->where('status', 'ready')
                    ->first(fn ($item) => $item->pivot?->role === 'primary');

                return $image
                    ? url(\Illuminate\Support\Facades\Storage::disk($image->disk)->url($image->path)) . '?t=' . time()
                    : null;
            }),
        ];
    }
}
