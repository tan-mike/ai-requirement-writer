<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SkillController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'data' => $request->user()->personas()->latest()->get()
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:json',
            'is_global' => 'required|boolean',
        ]);

        $content = json_decode(file_get_contents($request->file('file')->getRealPath()), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['message' => 'Invalid JSON format.'], 422);
        }

        // Validate JSON structure
        if (!isset($content['name'], $content['role'], $content['system_prompt'])) {
            return response()->json(['message' => 'JSON must include name, role, and system_prompt.'], 422);
        }

        if (!in_array($content['role'], ['lead', 'reviewer'])) {
            return response()->json(['message' => 'Role must be lead or reviewer.'], 422);
        }

        $persona = $request->user()->personas()->create([
            'name' => $content['name'],
            'role' => $content['role'],
            'system_prompt' => $content['system_prompt'],
            'cost_multiplier' => $content['cost_multiplier'] ?? 1.0,
            'slug' => Str::slug($content['name'] . '-' . uniqid()),
            'is_global' => $request->boolean('is_global'),
            'is_active' => true,
        ]);

        return response()->json([
            'data' => $persona,
            'message' => 'Skill uploaded successfully.',
        ], 201);
    }

    public function destroy(Request $request, Persona $persona)
    {
        if ($persona->user_id !== $request->user()->id) {
            abort(403, 'You can only delete skills you created.');
        }

        $persona->delete();

        return response()->json(null, 204);
    }
}
