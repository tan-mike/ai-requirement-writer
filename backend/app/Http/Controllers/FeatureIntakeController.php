<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureIntakeController extends Controller
{
    public function store(Request $request, Feature $feature): JsonResponse
    {
        $data = $request->validate([
            'fields' => ['required', 'array'],
        ]);

        $intake = $feature->intake()->updateOrCreate(
            ['feature_id' => $feature->id],
            ['fields' => $data['fields']]
        );

        $feature->update(['status' => Feature::STATUS_READY]);

        return response()->json(['data' => $intake], 201);
    }
}
