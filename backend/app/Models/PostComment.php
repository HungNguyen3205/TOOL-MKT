<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'post_id',
        'facebook_page_id',
        'parent_comment_id',
        'source',
        'content',
        'status',
        'scheduled_at',
        'published_at',
        'facebook_comment_id',
        'facebook_comment_url',
        'attempt_count',
        'last_attempt_at',
        'last_error_code',
        'last_error_message',
        'schedule_type',
        'delay_minutes',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'last_attempt_at' => 'datetime',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PUBLISHING = 'publishing';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function facebookPage()
    {
        return $this->belongsTo(FacebookPage::class);
    }

    public function parentComment()
    {
        return $this->belongsTo(PostComment::class, 'parent_comment_id');
    }

    public function attempts()
    {
        return $this->hasMany(CommentPublicationAttempt::class);
    }
}
