<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\GeminiGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class ProcessRepositoryJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Project $project)
    {
    }

    public function handle(GeminiGenerationService $gemini): void
    {
        $targetPath = $this->project->repository_path;
        $tempDir = null;

        if (!$targetPath && $this->project->repository_url) {
            $tempDir = storage_path('app/repos/' . $this->project->id);
            File::ensureDirectoryExists($tempDir);
            
            // Clone the repo
            $process = Process::run("git clone {$this->project->repository_url} .", $tempDir);
            
            if ($process->failed()) {
                \Illuminate\Support\Facades\Log::error("Failed to clone repository: " . $process->errorOutput());
                return;
            }
            $targetPath = $tempDir;
        }

        if (!$targetPath || !File::exists($targetPath)) {
            return;
        }

        $context = $this->extractContext($targetPath);
        $summary = $gemini->generateArchitectureSummary($context);

        $this->project->update(['architecture_summary' => $summary]);

        if ($tempDir) {
            File::deleteDirectory($tempDir);
        }
    }

    private function extractContext(string $path): string
    {
        $context = "Project Directory Structure:\n";
        
        // List files (limited)
        $files = File::allFiles($path);
        foreach (array_slice($files, 0, 100) as $file) {
            $context .= "- " . $file->getRelativePathname() . "\n";
        }

        // Read key files
        $keyFiles = [
            'README.md',
            'composer.json',
            'package.json',
            'artisan',
            'next.config.ts',
            'next.config.js',
            'tsconfig.json',
        ];

        foreach ($keyFiles as $kf) {
            $fullPath = $path . DIRECTORY_SEPARATOR . $kf;
            if (File::exists($fullPath)) {
                $content = File::get($fullPath);
                $context .= "\n--- FILE: {$kf} ---\n" . substr($content, 0, 2000) . "\n";
            }
        }

        return $context;
    }
}
