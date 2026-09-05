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
        'brand_type',
        'website',
        'hotline',
        'email',
        'address',
        'description',
        'products_services',
        'positioning',
        'unique_value_proposition',
        'brand_story',
        'brand_personality',
        'target_audience',
        'competitive_advantages',
        'customer_pain_points',
        'customer_desires',
        'customer_objections',
        'tone',
        'slogan',
        'default_cta',
        'default_hashtags',
        'required_keywords',
        'prohibited_terms',
        'writing_rules',
        'default_language',
        'emoji_limit',
        'preferred_addressing',
        'platform_rules',
        'profile_completeness',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'default_hashtags' => 'array',
        'required_keywords' => 'array',
        'prohibited_terms' => 'array',
        'writing_rules' => 'array',
        'service_areas' => 'array',
        'competitive_advantages' => 'array',
        'customer_pain_points' => 'array',
        'customer_desires' => 'array',
        'customer_objections' => 'array',
        'platform_rules' => 'array',
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
    public function knowledgeItems()
    {
        return $this->hasMany(BrandKnowledgeItem::class);
    }

    public function contentExamples()
    {
        return $this->hasMany(BrandContentExample::class);
    }

    public function versions()
    {
        return $this->hasMany(BrandVersion::class);
    }
}
