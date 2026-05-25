<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserSettingsController extends Controller
{
    public function show(Request $request)
    {
        return response()->json([
            'data' => [
                'free_tier' => $request->user()->free_tier,
                'gemini_api_key' => $request->user()->gemini_api_key,
                'tour_flags' => $request->user()->tour_flags ?? [],
            ]
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'free_tier' => 'nullable|boolean',
            'gemini_api_key' => 'nullable|string|max:500',
            'tour_flags' => 'nullable|array',
        ]);

        $request->user()->update($validated);

        return response()->json([
            'data' => $request->user()->only('free_tier', 'tour_flags'),
            'message' => 'Settings updated successfully.',
        ]);
    }
}
