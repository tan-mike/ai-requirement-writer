<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureStateGraph extends Model
{
    protected $fillable = [
        'feature_id',
        'current_node',
        'context_data',
        'is_paused'
    ];

    protected $casts = [
        'context_data' => 'array',
        'is_paused' => 'boolean'
    ];

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }
}
