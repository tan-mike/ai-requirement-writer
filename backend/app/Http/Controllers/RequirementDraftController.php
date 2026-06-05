<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use App\Models\RequirementDraft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequirementDraftController extends Controller
{
    private function checkAccess(Request $request, Feature $feature, ?RequirementDraft $draft = null): void
    {
        $user = $request->user();
        $project = $feature->project;
        
        if ($project->user_id !== $user->id && !$project->is_curated) {
            abort(403, 'Forbidden');
        }

        if ($draft) {
            if ($draft->feature_id !== $feature->id) {
                abort(403, 'Forbidden');
            }

            if ($project->is_curated && $draft->user_id !== $user->id) {
                abort(403, 'Forbidden');
            }
        }
    }

    public function index(Request $request, Feature $feature): JsonResponse
    {
        $this->checkAccess($request, $feature);

        $query = $feature->drafts()->with('critiques.persona')->latest();
        
        if ($feature->project->is_curated) {
            $query->where('user_id', $request->user()->id);
        }

        $drafts = $query->get()->groupBy('type');

        return response()->json(['data' => $drafts]);
    }

    public function show(Request $request, Feature $feature, RequirementDraft $draft): JsonResponse
    {
        $this->checkAccess($request, $feature, $draft);

        return response()->json(['data' => $draft->load('critiques.persona')]);
    }

    public function update(Request $request, Feature $feature, RequirementDraft $draft): JsonResponse
    {
        $this->checkAccess($request, $feature, $draft);

        $request->validate([
            'content' => ['required', 'string'],
        ]);

        $draft->update(['content' => $request->content]);

        return response()->json(['data' => $draft->fresh()]);
    }

    public function approve(Request $request, Feature $feature, RequirementDraft $draft): JsonResponse
    {
        $this->checkAccess($request, $feature, $draft);

        if ($feature->status === Feature::STATUS_COMPLETED) {
            return response()->json(['message' => 'Cannot approve drafts for a finalized feature.'], 422);
        }

        // Simple approve for now, we'll need to update GraphOrchestratorService later if needed
        $draft->approve();
        return response()->json(['data' => $draft->fresh()]);
    }
}
