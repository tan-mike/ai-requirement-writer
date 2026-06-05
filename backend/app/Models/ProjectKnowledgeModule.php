<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectKnowledgeModule extends Model {
    use HasUuids;
    protected $fillable = ['project_id', 'type', 'title', 'content', 'metadata', 'embedding', 'status'];
    protected $casts = ['metadata' => 'array', 'embedding' => 'array'];

    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }
}
