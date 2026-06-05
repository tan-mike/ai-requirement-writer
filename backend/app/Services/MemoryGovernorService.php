<?php
namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Collection;

class MemoryGovernorService {
    /**
     * Retrieve modules relevant to the current user prompt.
     * Basic implementation uses keyword matching for Phase 1.
     */
    public function getRelevantContext(Project $project, string $prompt): Collection {
        $keywords = $this->extractKeywords($prompt);
        
        return $project->knowledgeModules()
            ->where('status', 'active')
            ->get()
            ->filter(function ($module) use ($keywords) {
                if (empty($keywords)) return true;
                foreach ($keywords as $word) {
                    if (stripos($module->content, $word) !== false || stripos($module->title, $word) !== false) {
                        return true;
                    }
                }
                return false;
            });
    }

    private function extractKeywords(string $prompt): array {
        // Simple filter for significant words
        $words = explode(' ', strtolower(preg_replace('/[^a-z0-9 ]/', '', $prompt)));
        return array_filter($words, fn($w) => strlen($w) > 3);
    }

    public function formatContextForPrompt(Collection $modules): string {
        if ($modules->isEmpty()) return "No existing project context found.";

        return $modules->map(function ($m) {
            return "### MODULE: {$m->title} ({$m->type})\n{$m->content}";
        })->implode("\n\n---\n\n");
    }
}
