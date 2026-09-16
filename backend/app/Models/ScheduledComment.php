<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduledComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'facebook_page_id',
        'publication_id',
        'message',
        'image_path',
        'scheduled_at',
        'status',
        'external_comment_id',
        'last_error',
        'created_by'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function facebookPage()
    {
        return $this->belongsTo(FacebookPage::class);
    }

    public function publication()
    {
        return $this->belongsTo(Publication::class);
    }
}
