<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'data' => $request->user()->teams()->with('owner')->get()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);

        $team = Team::create([
            'name' => $request->name,
            'code' => strtoupper(Str::random(6)),
            'owner_id' => $request->user()->id,
        ]);

        $team->users()->attach($request->user()->id);
        
        // Auto-switch to new team
        $request->user()->update(['current_team_id' => $team->id]);

        return response()->json(['data' => $team->load('owner')], 201);
    }

    public function join(Request $request)
    {
        $request->validate(['code' => 'required|string|size:6']);

        $team = Team::where('code', strtoupper($request->code))->firstOrFail();

        if ($team->users()->where('user_id', $request->user()->id)->exists()) {
            return response()->json(['message' => 'You are already a member of this team.'], 422);
        }

        $team->users()->attach($request->user()->id);
        
        // Auto-switch to joined team
        $request->user()->update(['current_team_id' => $team->id]);

        return response()->json(['data' => $team->load('owner')]);
    }

    public function switch(Request $request)
    {
        $request->validate(['team_id' => 'nullable|exists:teams,id']);

        if ($request->team_id) {
            $team = $request->user()->teams()->where('team_id', $request->team_id)->firstOrFail();
            $request->user()->update(['current_team_id' => $team->id]);
        } else {
            $request->user()->update(['current_team_id' => null]);
        }

        return response()->json(['message' => 'Team switched successfully.']);
    }
}
