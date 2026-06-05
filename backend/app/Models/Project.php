<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Project extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    const STATUS_ACTIVE = 'active';
    const STATUS_ARCHIVED = 'archived';
    const STATUS_PROCESSING = 'processing';
    const STATUS_READY = 'ready';
    const STATUS_FAILED = 'error';

    protected $fillable = [
        'user_id',
        'team_id',
        'name',
        'description',
        'type',
        'status',
        'is_curated',
        'category',
        'repository_url',
        'repository_path',
        'architecture_summary',
    ];

    protected $attributes = [
        'status' => 'active',
        'is_curated' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_curated' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(Feature::class);
    }

    public function projectContexts(): BelongsToMany
    {
        return $this->belongsToMany(ProjectContext::class, 'project_project_context');
    }

    public function knowledgeModules(): HasMany
    {
        return $this->hasMany(ProjectKnowledgeModule::class);
    }
}
