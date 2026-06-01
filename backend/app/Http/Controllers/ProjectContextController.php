<?php

namespace App\Http\Controllers;

use App\Models\ProjectContext;
use Illuminate\Http\Request;

class ProjectContextController extends Controller
{
    public function index(Request $request)
    {
        // Return all contexts globally
        return ProjectContext::orderBy('created_at', 'desc')->get();
    }

    public function store(Request $request)
    {
        abort(403, 'Contexts are managed globally via Google Drive. Direct uploads are disabled.');
    }

    public function update(Request $request, ProjectContext $context)
    {
        abort(403, 'Contexts are managed globally via Google Drive. Direct modifications are disabled.');
    }

    public function destroy(Request $request, ProjectContext $context)
    {
        abort(403, 'Contexts are managed globally via Google Drive. Deletions are disabled.');
    }

    public function history(ProjectContext $context)
    {
        return response()->json([
            'data' => $context->audits()->with('user')->latest()->get()
        ]);
    }
}
