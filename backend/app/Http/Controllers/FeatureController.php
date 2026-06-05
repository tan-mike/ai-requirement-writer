<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        return response()->json(['data' => $project->features()->latest()->get()]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'lead_persona_id' => ['required', 'exists:personas,id'],
            'template_id' => ['required', 'exists:templates,id'],
            'mode' => ['required', 'in:template,conversational'],
        ]);

        $feature = $project->features()->create($data);

        return response()->json(['data' => $feature], 201);
    }

    public function show(Feature $feature): JsonResponse
    {
        return response()->json(['data' => $feature->load(['leadPersona', 'template', 'intake'])]);
    }

    public function update(Request $request, Feature $feature): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'string'],
        ]);

        $feature->update($data);

        return response()->json(['data' => $feature]);
    }

    public function destroy(Feature $feature): JsonResponse
    {
        $feature->delete();
        return response()->json(null, 204);
    }
}
