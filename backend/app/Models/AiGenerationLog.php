<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGenerationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'project_id',
        'type',
        'model',
        'system_prompt',
        'input_prompt',
        'output_text',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'execution_time_ms',
        'status',
        'error_message',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
