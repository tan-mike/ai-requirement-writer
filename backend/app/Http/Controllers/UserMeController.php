<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserMeController extends Controller
{
    public function __invoke(Request $request)
    {
        return response()->json([
            'data' => $request->user()->load('currentTeam')
        ]);
    }
}
