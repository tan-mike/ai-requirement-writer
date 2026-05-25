<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Relations\HasMany;

class RequirementDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'type',
        'version',
        'content',
        'status',
        'lead_persona_id',
        'reviewer_persona_ids',
    ];

    protected $casts = [
        'reviewer_persona_ids' => 'array',
        'version' => 'integer',
    ];

    protected $attributes = [
        'status' => 'drafting',
        'version' => 1,
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function leadPersona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'lead_persona_id');
    }

    public function critiques(): HasMany
    {
        return $this->hasMany(DraftCritique::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function approve(): void
    {
        $this->update(['status' => 'approved']);
    }
}
