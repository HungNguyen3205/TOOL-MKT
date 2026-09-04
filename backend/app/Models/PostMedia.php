<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class PostMedia extends Pivot
{
    protected $table = 'post_media';

    protected $fillable = [
        'post_id',
        'media_asset_id',
        'position',
        'role'
    ];
}
