<?php

namespace App\Http\Controllers;

use App\Models\ProjectContext;
use Illuminate\Http\Request;

class ProjectContextController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        return ProjectContext::where(function($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->orWhere('team_id', $user->current_team_id);
        })
        ->orderBy('created_at', 'desc')
        ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required_without:file|string|nullable',
            'file' => 'required_without:content|file|max:2048',
            'type' => 'nullable|string|max:50',
            'is_team_shared' => 'nullable|boolean',
        ]);

        $content = $request->content;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $mime = $file->getMimeType();
            if (!str_starts_with($mime, 'text/') && !in_array($mime, ['application/json', 'application/xml', 'application/x-markdown'])) {
                $ext = strtolower($file->getClientOriginalExtension());
                if (!in_array($ext, ['txt', 'md', 'markdown', 'json', 'xml', 'csv'])) {
                    abort(422, 'Unsupported file type.');
                }
            }
            $content = file_get_contents($file->getRealPath());
        }

        $context = $request->user()->projectContexts()->create([
            'name' => $validated['name'],
            'content' => $content,
            'type' => $validated['type'] ?? 'file',
            'team_id' => $request->boolean('is_team_shared') ? $request->user()->current_team_id : null,
        ]);

        return response()->json($context, 201);
    }

    public function update(Request $request, ProjectContext $context)
    {
        $user = $request->user();

        // Allow updates if user is owner OR user is in the team the context is shared with
        if ($context->user_id !== $user->id && $context->team_id !== $user->current_team_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'content' => 'required|string',
            'is_team_shared' => 'nullable|boolean',
        ]);

        $updateData = ['content' => $validated['content']];
        if (isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }

        // Only the owner can change sharing settings
        if ($context->user_id === $user->id && isset($validated['is_team_shared'])) {
            $updateData['team_id'] = $validated['is_team_shared'] ? $user->current_team_id : null;
        }

        $context->update($updateData);

        return response()->json($context);
    }

    public function destroy(Request $request, ProjectContext $context)
    {
        if ($context->user_id !== $request->user()->id) {
            abort(403, 'Only the owner can delete a context.');
        }

        $context->delete();

        return response()->json(null, 204);
    }

    public function history(ProjectContext $context)
    {
        return response()->json([
            'data' => $context->audits()->with('user')->latest()->get()
        ]);
    }
}
