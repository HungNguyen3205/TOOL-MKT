<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MediaVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'media_asset_id',
        'variant_type',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function mediaAsset()
    {
        return $this->belongsTo(MediaAsset::class);
    }
}
