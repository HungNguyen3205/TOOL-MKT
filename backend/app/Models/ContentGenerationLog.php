<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToWorkspace;

class ContentGenerationLog extends Model
{
    use HasFactory, BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'brand_id',
        'content_template_id',
        'provider',
        'model',
        'prompt_version',
        'prompt_hash',
        'request_data',
        'number_of_versions',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'estimated_cost',
        'currency',
        'duration_ms',
        'quality_score_average',
        'successful',
        'error_code',
        'retried'
    ];

    protected $casts = [
        'request_data' => 'json',
        'successful' => 'boolean',
        'retried' => 'boolean',
        'estimated_cost' => 'decimal:6',
        'quality_score_average' => 'decimal:2',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }
}
