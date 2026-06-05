<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Feature extends Model
{
    use HasFactory;

    const STATUS_INTAKE = 'intake';
    const STATUS_GENERATING_BRD = 'generating_brd';
    const STATUS_READY = 'ready';
    const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'status',
        'lead_persona_id',
        'template_id',
        'mode',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function intake(): HasOne
    {
        return $this->hasOne(FeatureIntake::class);
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(RequirementDraft::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function aiGenerationLogs(): HasMany
    {
        return $this->hasMany(AiGenerationLog::class);
    }

    public function leadPersona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'lead_persona_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function stateGraph(): HasOne
    {
        return $this->hasOne(FeatureStateGraph::class);
    }
}
