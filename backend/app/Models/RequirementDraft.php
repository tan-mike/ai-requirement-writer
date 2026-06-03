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
        'user_id',
        'type',
        'version',
        'title',
        'description',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        return $query->whereIn('status', ['approved', 'committed']);
    }

    public function approve(): void
    {
        $this->update(['status' => 'approved']);
    }
}
