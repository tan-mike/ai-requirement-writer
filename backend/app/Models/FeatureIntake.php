<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureIntake extends Model
{
    use HasFactory;

    protected $fillable = [
        'feature_id',
        'fields',
    ];

    protected $casts = [
        'fields' => 'array',
    ];

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }
}
