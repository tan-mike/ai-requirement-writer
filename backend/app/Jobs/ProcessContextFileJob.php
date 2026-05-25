<?php

namespace App\Jobs;

use App\Models\Project;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessContextFileJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 120;

    public function __construct(
        public Project $project,
        public string $filePath
    ) {}

    public function handle(): void
    {
        try {
            if (!Storage::exists($this->filePath)) {
                Log::error("Context file not found at: {$this->filePath}");
                $this->project->update(['status' => Project::STATUS_FAILED]);
                return;
            }

            $content = Storage::get($this->filePath);
            
            $this->project->update([
                'architecture_summary' => $content,
                'status' => Project::STATUS_READY,
            ]);

            Storage::delete($this->filePath);
        } catch (\Throwable $e) {
            Log::error("Failed to process context file: " . $e->getMessage());
            $this->project->update(['status' => Project::STATUS_FAILED]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->project->update(['status' => Project::STATUS_FAILED]);
        \Illuminate\Support\Facades\Log::error("Context File Job Failed Permanently: " . $exception->getMessage());
    }
}
