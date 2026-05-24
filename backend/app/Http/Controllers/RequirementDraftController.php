<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\RequirementDraft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequirementDraftController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        if ($project->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $drafts = $project->drafts()->latest()->get()->groupBy('type');

        return response()->json(['data' => $drafts]);
    }

    public function show(Request $request, Project $project, RequirementDraft $draft): JsonResponse
    {
        if ($project->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($draft->project_id !== $project->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json(['data' => $draft]);
    }

    public function update(Request $request, Project $project, RequirementDraft $draft): JsonResponse
    {
        if ($project->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($draft->project_id !== $project->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'content' => ['required', 'string'],
        ]);

        $draft->update(['content' => $request->content]);

        return response()->json(['data' => $draft->fresh()]);
    }

    public function approve(Request $request, Project $project, RequirementDraft $draft): JsonResponse
    {
        if ($project->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($draft->project_id !== $project->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $draft->approve();

        $this->checkProjectCompletion($project);

        return response()->json(['data' => $draft->fresh()]);
    }

    private function checkProjectCompletion(Project $project): void
    {
        $requiredTypes = ['brd', 'stories', 'spec'];

        $allApproved = collect($requiredTypes)->every(function ($type) use ($project) {
            return $project->drafts()->where('type', $type)->where('status', 'approved')->exists();
        });

        if ($allApproved) {
            $project->update(['status' => Project::STATUS_COMPLETE]);
        }
    }
}
