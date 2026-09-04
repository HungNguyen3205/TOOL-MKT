<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\BelongsToWorkspace;

class MediaAsset extends Model
{
    use HasFactory, SoftDeletes, BelongsToWorkspace;

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
