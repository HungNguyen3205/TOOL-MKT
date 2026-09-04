<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\SoftDeletes;

class BrandContentExample extends Model
{
    use HasFactory, SoftDeletes, BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'brand_id',
        'title',
        'content',
        'example_type', // good or bad
        'explanation',
        'objective',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
