<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    use HasFactory;

    const STATUS_DRAFT = 'draft';
    const STATUS_PROCESSING = 'processing';
    const STATUS_READY = 'ready';
    const STATUS_FAILED = 'error';

    protected $fillable = [
        'user_id',
        'template_id',
        'name',
        'type',
        'mode',
        'status',
        'repository_url',
        'repository_path',
        'architecture_summary',
        'lead_persona_id'
    ];

    protected $attributes = [
        'mode' => 'template',
        'status' => 'draft',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leadPersona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'lead_persona_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function intake(): HasOne
    {
        return $this->hasOne(ProjectIntake::class);
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(RequirementDraft::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function projectContexts(): BelongsToMany
    {
        return $this->belongsToMany(ProjectContext::class, 'project_project_context');
    }
}
