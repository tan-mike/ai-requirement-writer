<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Persona extends Model
{
    protected $fillable = [
        'user_id',
        'slug',
        'name',
        'role',
        'system_prompt',
        'cost_multiplier',
        'is_global',
        'is_active',
        'google_drive_id',
    ];

    protected $casts = [
        'cost_multiplier' => 'float',
        'is_active' => 'boolean',
        'is_global' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVisibleTo(Builder $query, ?int $userId = null): Builder
    {
        // Return all personas globally, ignoring user ownership
        return $query;
    }
}
