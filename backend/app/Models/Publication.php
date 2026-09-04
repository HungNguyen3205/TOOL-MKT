<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToWorkspace;

class Publication extends Model
{
    use HasFactory, BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'post_id',
        'facebook_page_id',
        'platform',
        'publication_type',
        'status',
        'content_snapshot',
        'content_hash',
        'idempotency_key',
        'requested_by',
        'queued_at',
        'processing_at',
        'published_at',
        'failed_at',
        'external_post_id',
        'external_post_url',
        'attempts_count',
        'last_error_code',
        'last_error_message'
    ];

    protected $casts = [
        'content_snapshot' => 'json',
        'queued_at' => 'datetime',
        'processing_at' => 'datetime',
        'published_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }


    public function facebookPage()
    {
        return $this->belongsTo(FacebookPage::class);
    }


    public function attempts()
    {
        return $this->hasMany(PublicationAttempt::class);
    }


    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }
}
