<?php

namespace App\Http\Controllers;

use App\Models\ProjectContext;
use Illuminate\Http\Request;

class ProjectContextController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->projectContexts()->orderBy('created_at', 'desc')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required_without:file|string|nullable',
            'file' => 'required_without:content|file|max:2048', // Allow all extensions, check content in code
            'type' => 'nullable|string|max:50',
        ]);

        $content = $request->content;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            // Basic security check for text-like content
            $mime = $file->getMimeType();
            if (!str_starts_with($mime, 'text/') && !in_array($mime, ['application/json', 'application/xml', 'application/x-markdown'])) {
                // If mime is generic binary, try to read it anyway if it looks like a text extension
                $ext = strtolower($file->getClientOriginalExtension());
                if (!in_array($ext, ['txt', 'md', 'markdown', 'json', 'xml', 'csv'])) {
                    abort(422, 'Unsupported file type. Please upload text, markdown, json, or xml.');
                }
            }
            $content = file_get_contents($file->getRealPath());
        }

        $context = $request->user()->projectContexts()->create([
            'name' => $validated['name'],
            'content' => $content,
            'type' => $validated['type'] ?? 'file',
        ]);

        return response()->json($context, 201);
    }

    public function update(Request $request, ProjectContext $context)
    {
        if ($context->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $context->update($validated);

        return response()->json($context);
    }

    public function destroy(Request $request, ProjectContext $context)
    {
        if ($context->user_id !== $request->user()->id) {
            abort(403);
        }

        $context->delete();

        return response()->json(null, 204);
    }
}
