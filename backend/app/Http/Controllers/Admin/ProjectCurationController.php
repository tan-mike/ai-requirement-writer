<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectCurationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = Project::with(['user', 'team'])->latest()->get();
        return response()->json(['data' => $projects]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'context_ids' => ['nullable', 'array'],
            'context_ids.*' => ['exists:project_contexts,id'],
        ]);

        $project = Project::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'is_curated' => true,
            'type' => 'webapp', // default
            'status' => Project::STATUS_READY,
        ]);

        if (!empty($data['context_ids'])) {
            $project->projectContexts()->sync($data['context_ids']);
        }

        return response()->json(['data' => $project], 201);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'is_curated' => ['required', 'boolean'],
            'category' => ['nullable', 'string', 'max:255'],
        ]);

        $project->update($data);

        return response()->json(['data' => $project]);
    }
}
