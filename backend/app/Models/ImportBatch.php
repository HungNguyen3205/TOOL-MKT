<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToWorkspace;

class ImportBatch extends Model
{
    use HasFactory, BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'brand_id',
        'facebook_page_id',
        'original_filename',
        'file_type',
        'timezone',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'imported_rows',
        'status',
        'error_message',
        'options',
        'created_by'
    ];

    protected $casts = [
        'options' => 'array',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
