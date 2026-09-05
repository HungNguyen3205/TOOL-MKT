<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes, BelongsToWorkspace;

    protected $fillable = [
        'brand_id',
        'created_by',
        'title',
        'content',
        'cta',
        'hashtags',
        'objective',
        'tone',
        'content_length',
        'source',
        'status',
        'ai_model',
        'ai_provider',
        'source_input',
        'selected_version',
        'last_saved_at',
        'content_template_id', // added for Sprint 3
        'published_at',
        'last_publication_status',
        'last_facebook_post_id',
        'post_type',
        'image_prompt',
        'image_path',
        'final_image_path',
        'facebook_page_id',
        'scheduled_at',
        'timezone',
        'facebook_post_id',
        'generation_error',
        'publish_error',
        'retry_count',
        'design_format',
        'design_title',
        'design_layout',
        'design_visual',
        'design_color',
        'design_suggestion',
    ];

    // Status Constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_GENERATING_CONTENT = 'generating_content';
    public const STATUS_GENERATING_IMAGE = 'generating_image';
    public const STATUS_READY = 'ready';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PUBLISHING = 'publishing';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_FAILED = 'failed';
    public const STATUS_IMAGE_FAILED = 'image_failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $casts = [
        'hashtags' => 'array',
        'source_input' => 'array',
        'quality_result' => 'array',
        'last_saved_at' => 'datetime',
        'published_at' => 'datetime',
        'quality_checked_at' => 'datetime',
        'submitted_for_review_at' => 'datetime',
        'approved_at' => 'datetime',
        'ready_at' => 'datetime',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function contentTemplate()
    {
        return $this->belongsTo(ContentTemplate::class);
    }

    public function publicationLogs()
    {
        return $this->hasMany(PublicationLog::class);
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function media()
    {
        return $this->belongsToMany(MediaAsset::class, 'post_media')
            ->withPivot(['position', 'role'])
            ->withTimestamps()
            ->orderBy('post_media.position');
    }
}
