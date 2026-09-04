<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToWorkspace;

class BrandVersion extends Model
{
    use HasFactory, BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'brand_id',
        'version_number',
        'snapshot',
        'change_summary',
        'created_by',
    ];

    protected $casts = [
        'snapshot' => 'array',
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
