<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserMeController extends Controller
{
    public function __invoke(Request $request)
    {
        $creationDisabled = \App\Models\Setting::where('key', 'creation_disabled')->value('value') ?? config('projects.creation_disabled');

        return response()->json([
            'data' => $request->user()->load('currentTeam'),
            'settings' => [
                'creation_disabled' => filter_var($creationDisabled, FILTER_VALIDATE_BOOLEAN),
            ]
        ]);
    }
}
