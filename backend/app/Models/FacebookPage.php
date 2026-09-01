<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FacebookPage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'page_id',
        'page_name',
        'page_username',
        'page_picture_url',
        'access_token',
        'token_expires_at',
        'granted_scopes',
        'permissions_checked_at',
        'connected_by',
        'is_active',
        'connection_status',
        'last_verified_at',
        'last_error_code',
        'last_error_message'
    ];

    protected $hidden = [
        'access_token'
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'granted_scopes' => 'json',
        'token_expires_at' => 'datetime',
        'permissions_checked_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function publicationLogs()
    {
        return $this->hasMany(PublicationLog::class);
    }
}
