<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DraftScore extends Model
{
    protected $fillable = [
        'requirement_draft_id',
        'user_id',
        'criterion_scores',
        'overall_score',
        'recipe_snapshot',
    ];

    protected $casts = [
        'criterion_scores' => 'array',
        'recipe_snapshot' => 'array',
        'overall_score' => 'decimal:1',
    ];

    public function draft(): BelongsTo
    {
        return $this->belongsTo(RequirementDraft::class, 'requirement_draft_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
