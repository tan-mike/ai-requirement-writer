<?php
namespace App\Services;

use App\Models\Feature;
use App\Models\FeatureStateGraph;
use App\Models\RequirementDraft;
use App\Models\ProjectKnowledgeModule;
use Illuminate\Support\Facades\DB;

class GraphOrchestratorService {
    public function transition(Feature $feature, string $targetNode, array $data = []): void {
        DB::transaction(function () use ($feature, $targetNode, $data) {
            $graph = $feature->stateGraph()->firstOrCreate(
                ['feature_id' => $feature->id],
                ['current_node' => 'idle', 'context_data' => []]
            );

            $graph->update([
                'current_node' => $targetNode,
                'context_data' => array_merge($graph->context_data, $data)
            ]);

            $this->handleNodeEntry($feature, $targetNode, $graph);
        });
    }

    protected function handleNodeEntry(Feature $feature, string $node, FeatureStateGraph $graph): void {
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
                $this->commitDraftToKnowledgeBase($feature, $graph, $draft);
                break;
        }
    }

    private function commitDraftToKnowledgeBase(Feature $feature, FeatureStateGraph $graph, ?RequirementDraft $draft): void {
        if (!$draft) {
            throw new \Exception("Cannot commit draft to knowledge base: RequirementDraft not found.");
        }

        ProjectKnowledgeModule::updateOrCreate(
            [
                'project_id' => $feature->project_id,
                'title' => $feature->name . " - " . ucfirst($draft->type) . " (Version {$draft->version})",
                'type' => 'requirement'
            ],
            [
                'content' => $draft->content,
                'metadata' => [
                    'feature_id' => $feature->id,
                    'draft_id' => $draft->id, 
                    'version' => $draft->version
                ],
                'status' => 'active'
            ]
        );

        $feature->update(['status' => Feature::STATUS_READY]);
    }
}
