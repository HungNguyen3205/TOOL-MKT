<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PublicationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'facebook_page_id',
        'action',
        'status',
        'request_type',
        'facebook_post_id',
        'facebook_post_url',
        'error_code',
        'error_message',
        'http_status',
        'response_metadata',
        'attempted_at',
        'published_at'
    ];

    protected $casts = [
        'response_metadata' => 'json',
        'attempted_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function facebookPage()
    {
        return $this->belongsTo(FacebookPage::class);
    }
}
