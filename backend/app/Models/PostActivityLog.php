<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToWorkspace;

class PostActivityLog extends Model
{
    use HasFactory, BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'post_id',
        'action',
        'from_status',
        'to_status',
        'metadata',
        'performed_by'
    ];

    protected $casts = [
        'metadata' => 'array',
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
