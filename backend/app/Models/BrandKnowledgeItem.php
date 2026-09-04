<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\SoftDeletes;

class BrandKnowledgeItem extends Model
{
    use HasFactory, SoftDeletes, BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'brand_id',
        'title',
        'category',
        'content',
        'source_url',
        'is_verified',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }


    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }
}
