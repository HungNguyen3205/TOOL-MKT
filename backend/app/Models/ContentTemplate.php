<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentTemplate extends Model
{
    use HasFactory, SoftDeletes, BelongsToWorkspace;

    protected $fillable = [
        'brand_id',
        'name',
        'description',
        'objective',
        'opening_style',
        'body_structure',
        'cta_instruction',
        'hashtag_instruction',
        'additional_instruction',
        'example_content',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'body_structure' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }
}
