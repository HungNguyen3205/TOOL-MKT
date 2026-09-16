<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommentPublicationAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_comment_id',
        'attempt_number',
        'status',
        'http_status',
        'facebook_error_code',
        'facebook_error_subcode',
        'message',
        'response_meta',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'response_meta' => 'json',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function postComment()
    {
        return $this->belongsTo(PostComment::class);
    }
}
