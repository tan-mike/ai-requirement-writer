<?php
namespace App\Services;

use App\Models\Project;
use App\Models\ProjectStateGraph;
use App\Models\RequirementDraft;
use App\Models\ProjectKnowledgeModule;
use Illuminate\Support\Facades\DB;

class GraphOrchestratorService {
    public function transition(Project $project, string $targetNode, array $data = []): void {
        DB::transaction(function () use ($project, $targetNode, $data) {
            $graph = $project->stateGraph()->firstOrCreate(
                ['project_id' => $project->id],
                ['current_node' => 'idle', 'context_data' => []]
            );

            $graph->update([
                'current_node' => $targetNode,
                'context_data' => array_merge($graph->context_data, $data)
            ]);

            $this->handleNodeEntry($project, $targetNode, $graph);
        });
    }

    protected function handleNodeEntry(Project $project, string $node, ProjectStateGraph $graph): void {
        $data = $graph->context_data;
        $activeDraftId = $data['active_draft_id'] ?? null;
        $draft = $activeDraftId ? RequirementDraft::find($activeDraftId) : null;

        if ($draft) {
            $draft->update(['status' => $node]);
        }

        switch ($node) {
            case 'reviewing':
                // Logic to trigger critiques if not already done
                break;
            case 'refining':
                // Logic to signal user or auto-refine
                break;
            case 'approved':
                // Handle approval side effects
                break;
            case 'failed':
                // Handle failure side effects
                break;
            case 'committed':
                $this->commitDraftToKnowledgeBase($project, $graph, $draft);
                break;
        }
    }

    private function commitDraftToKnowledgeBase(Project $project, ProjectStateGraph $graph, ?RequirementDraft $draft): void {
        if (!$draft) {
            throw new \Exception("Cannot commit draft to knowledge base: RequirementDraft not found.");
        }

        ProjectKnowledgeModule::updateOrCreate(
            [
                'project_id' => $project->id,
                'title' => ucfirst($draft->type) . " (Version {$draft->version})",
                'type' => 'requirement'
            ],
            [
                'content' => $draft->content,
                'metadata' => ['draft_id' => $draft->id, 'version' => $draft->version],
                'status' => 'active'
            ]
        );

        $project->update(['status' => Project::STATUS_COMPLETE]);
    }
}
