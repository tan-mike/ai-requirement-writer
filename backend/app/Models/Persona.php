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
        return $query->where(function (Builder $q) use ($userId) {
            $q->whereNull('user_id')
              ->orWhere('is_global', true)
              ->when($userId, fn($sq) => $sq->orWhere('user_id', $userId));
        });
    }
}
