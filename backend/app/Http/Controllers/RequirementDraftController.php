<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\RequirementDraft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequirementDraftController extends Controller
{
    private function checkAccess(Request $request, Project $project, ?RequirementDraft $draft = null): void
    {
        $user = $request->user();
        
        if ($project->user_id !== $user->id && !$project->is_curated) {
            abort(403, 'Forbidden');
        }

        if ($draft) {
            if ($draft->project_id !== $project->id) {
                abort(403, 'Forbidden');
            }

            if ($project->is_curated && $draft->user_id !== $user->id) {
                abort(403, 'Forbidden');
            }
        }
    }

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->checkAccess($request, $project);

        $query = $project->drafts()->with('critiques.persona')->latest();
        
        if ($project->is_curated) {
            $query->where('user_id', $request->user()->id);
        }

        $drafts = $query->get()->groupBy('type');

        return response()->json(['data' => $drafts]);
    }

    public function show(Request $request, Project $project, RequirementDraft $draft): JsonResponse
    {
        $this->checkAccess($request, $project, $draft);

        return response()->json(['data' => $draft->load('critiques.persona')]);
    }

    public function update(Request $request, Project $project, RequirementDraft $draft): JsonResponse
    {
        $this->checkAccess($request, $project, $draft);

        $request->validate([
            'content' => ['required', 'string'],
        ]);

        $draft->update(['content' => $request->content]);

        return response()->json(['data' => $draft->fresh()]);
    }

    public function approve(Request $request, Project $project, RequirementDraft $draft): JsonResponse
    {
        $this->checkAccess($request, $project, $draft);

        try {
            app(\App\Services\GraphOrchestratorService::class)->transition($project, 'committed', [
                'active_draft_id' => $draft->id
            ]);
            return response()->json(['data' => $draft->fresh()]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to approve and commit: ' . $e->getMessage()], 500);
        }
    }
}
