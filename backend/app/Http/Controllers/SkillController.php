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
            'data' => Persona::visibleTo($request->user()->id)->latest()->get()
        ]);
    }

    public function upload(Request $request)
    {
        abort(403, 'Skills are managed globally via Google Drive. Direct uploads are disabled.');
    }

    public function destroy(Request $request, Persona $persona)
    {
        abort(403, 'Skills are managed globally via Google Drive. Deletions are disabled.');
    }
}
