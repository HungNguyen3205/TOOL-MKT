<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;

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
    ];

    protected $casts = [
        'hashtags' => 'array',
        'source_input' => 'array',
        'last_saved_at' => 'datetime',
        'published_at' => 'datetime',
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
}
