<?php

namespace App\Jobs;

use App\Models\Project;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ProcessContextFileJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Project $project,
        public string $filePath
    ) {}

    public function handle(): void
    {
        if (!Storage::exists($this->filePath)) {
            \Illuminate\Support\Facades\Log::error("Context file not found at: {$this->filePath}");
            $this->project->update(['status' => 'error']);
            return;
        }

        $content = Storage::get($this->filePath);
        
        $this->project->update([
            'architecture_summary' => $content,
            'status' => 'ready',
        ]);

        Storage::delete($this->filePath);
    }
}
