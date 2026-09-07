<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\BelongsToWorkspace;

class MediaAsset extends Model
{
    use HasFactory, SoftDeletes, BelongsToWorkspace;

    // Type Constants
    public const TYPE_IMAGE = 'image';
    public const TYPE_VIDEO = 'video';

    // Status Constants
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';
    public const STATUS_VIDEO_DRAFT_QUEUED = 'video_draft_queued';
    public const STATUS_VIDEO_DRAFT_PROCESSING = 'video_draft_processing';
    public const STATUS_VIDEO_DRAFT_READY = 'video_draft_ready';
    public const STATUS_VIDEO_DRAFT_FAILED = 'video_draft_failed';
    public const STATUS_VIDEO_FINAL_QUEUED = 'video_final_queued';
    public const STATUS_VIDEO_FINAL_PROCESSING = 'video_final_processing';
    public const STATUS_VIDEO_FINAL_READY = 'video_final_ready';
    public const STATUS_VIDEO_FINAL_FAILED = 'video_final_failed';

    protected $fillable = [
        'brand_id', 'uploaded_by', 'workspace_id',
        'type', 'status', 'disk', 'path',
        'original_name', 'stored_name', 'mime_type', 'extension', 'size_bytes',
        'width', 'height', 'duration_seconds', 'checksum',
        'title', 'alt_text', 'caption', 'tags', 'metadata',
        'error_code', 'error_message', 'processed_at'
    ];

    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
        'processed_at' => 'datetime',
    ];

    public function variants()
    {
        return $this->hasMany(MediaVariant::class);
    }

    public function posts()
    {
        return $this->belongsToMany(Post::class, 'post_media')
            ->withPivot(['position', 'role'])
            ->withTimestamps();
    }
}
