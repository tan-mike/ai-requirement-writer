<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ScoringConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => config('scoring')]);
    }
}
