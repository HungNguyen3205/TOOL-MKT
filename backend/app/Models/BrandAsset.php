<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BrandAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'media_asset_id',
        'asset_role',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function mediaAsset()
    {
        return $this->belongsTo(MediaAsset::class);
    }
}
