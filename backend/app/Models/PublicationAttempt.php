<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PublicationAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'publication_id',
        'attempt_number',
        'status',
        'http_status',
        'platform_error_code',
        'platform_error_subcode',
        'error_category',
        'error_message',
        'response_metadata',
        'started_at',
        'completed_at'
    ];

    protected $casts = [
        'response_metadata' => 'json',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function publication()
    {
        return $this->belongsTo(Publication::class);
    }
}
