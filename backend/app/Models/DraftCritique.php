<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DraftCritique extends Model
{
    protected $fillable = [
        'requirement_draft_id',
        'persona_id',
        'content',
        'status'
    ];

    public function draft(): BelongsTo
    {
        return $this->belongsTo(RequirementDraft::class, 'requirement_draft_id');
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }
}
