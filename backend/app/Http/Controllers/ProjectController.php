<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = $request->user()->projects()->latest()->get();

        return response()->json(['data' => $projects]);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $data = $request->validated();
        $contextFile = $request->file('context_file');
        $contextIds = $request->input('context_ids', []);
        
        unset($data['context_file'], $data['context_ids']);

        $project = $request->user()->projects()->create($data);

        // Link saved contexts
        if (!empty($contextIds)) {
            $project->projectContexts()->sync($contextIds);
        }

        if ($contextFile) {
            $project->update(['status' => Project::STATUS_PROCESSING]);
            $path = $contextFile->store('temp_context');
            \App\Jobs\ProcessContextFileJob::dispatch($project, $path);
        } elseif ($project->repository_url || $project->repository_path) {
            $project->update(['status' => Project::STATUS_PROCESSING]);
            \App\Jobs\ProcessRepositoryJob::dispatch($project);
        }

        return response()->json(['data' => $project], 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        if ($project->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json(['data' => $project->load('template')]);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        if ($project->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $project->delete();

        return response()->json(null, 204);
    }
}
