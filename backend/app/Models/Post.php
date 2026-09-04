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
    ];

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
