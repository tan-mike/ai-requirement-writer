<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectStateGraph extends Model {
    protected $fillable = ['project_id', 'current_node', 'context_data', 'is_paused'];
    protected $casts = ['context_data' => 'array', 'is_paused' => 'boolean'];

    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }
}
