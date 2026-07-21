<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserInviteController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'role' => ['nullable', 'string', Rule::in(['admin', 'member'])],
        ]);

        $user = User::create([
            'name' => explode('@', $request->email)[0],
            'email' => $request->email,
            'password' => null,
            'email_verified_at' => now(),
            'role' => $request->input('role', 'member'),
        ]);

        return response()->json([
            'message' => 'User invited successfully.',
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ], 201);
    }
}
