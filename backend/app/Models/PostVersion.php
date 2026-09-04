<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToWorkspace;

class PostVersion extends Model
{
    use HasFactory, BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'post_id',
        'version_number',
        'title',
        'content',
        'cta',
        'hashtags',
        'content_hash',
        'quality_score',
        'quality_status',
        'quality_result',
        'change_source',
        'change_summary',
        'created_by'
    ];

    protected $casts = [
        'hashtags' => 'array',
        'quality_result' => 'array',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }


    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }
}
