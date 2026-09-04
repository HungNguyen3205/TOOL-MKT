<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use HasFactory, SoftDeletes, BelongsToWorkspace;

    protected $fillable = [
        'name',
        'slug',
        'industry',
        'description',
        'products_services',
        'target_audience',
        'tone',
        'slogan',
        'default_cta',
        'default_hashtags',
        'required_keywords',
        'prohibited_terms',
        'writing_rules',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'default_hashtags' => 'array',
        'required_keywords' => 'array',
        'prohibited_terms' => 'array',
        'writing_rules' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function templates()
    {
        return $this->hasMany(ContentTemplate::class);
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
