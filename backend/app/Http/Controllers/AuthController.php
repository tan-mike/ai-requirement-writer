<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\DomainValidationService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Registration successful. Please check your email to verify your account.',
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ])->status(401);
        }

        $user = Auth::user();

        if (!$user->hasVerifiedEmail()) {
            Auth::logout();
            return response()->json([
                'message' => 'Please verify your email address before logging in.',
            ], 409);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json(['data' => ['user' => $this->userShape($user), 'token' => $token]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if (method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json(['message' => 'Logged out']);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'id' => ['required'],
            'hash' => ['required'],
            'expires' => ['required'],
            'signature' => ['required'],
        ]);

        // Manually validate signature by reconstructing
        if (!$this->hasValidSignature($request)) {
            return response()->json(['message' => 'Invalid or expired verification link.'], 403);
        }

        $user = User::findOrFail($request->id);

        if (!hash_equals(sha1($user->getEmailForVerification()), (string) $request->hash)) {
            return response()->json(['message' => 'Invalid verification link.'], 403);
        }

        // Re-check domain allowlist
        if (!app(DomainValidationService::class)->isDomainAllowed($user->email)) {
            return response()->json([
                'message' => 'This email domain is no longer accepted.',
            ], 422);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return response()->json(['message' => 'Email verified successfully. You may now log in.']);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $request->email)->first();

        if ($user && !$user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'message' => 'If an account exists with this email, a verification link has been sent.',
        ]);
    }

    public function changeEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'email_confirmation' => ['required', 'same:email'],
        ]);

        $user = $request->user();

        if ($user->google_id) {
            throw ValidationException::withMessages([
                'email' => ['Cannot change email for accounts linked to Google SSO. Contact an administrator.'],
            ]);
        }

        if (!app(DomainValidationService::class)->isDomainAllowed($request->email)) {
            throw ValidationException::withMessages([
                'email' => ['Email domain is not allowed.'],
            ]);
        }

        $user->update([
            'email' => $request->email,
            'email_verified_at' => null,
        ]);

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Email updated. Please verify your new email address.',
        ]);
    }

    private function hasValidSignature(Request $request): bool
    {
        // Reconstruct the full signed URL from the original request params
        $baseUrl = url()->route('verification.verify');

        // Build query string with params sorted alphabetically (matching Laravel's signing order)
        $params = [
            'expires' => $request->expires,
            'hash' => $request->hash,
            'id' => $request->id,
        ];
        ksort($params);

        $urlWithoutSignature = $baseUrl . '?' . http_build_query($params);

        // Compute expected signature the same way Laravel does
        $key = config('app.key');
        $expectedSignature = hash_hmac('sha256', $urlWithoutSignature, $key);

        return hash_equals($expectedSignature, (string) $request->signature)
            && $request->expires >= now()->timestamp;
    }

    private function userShape(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
        ];
    }
}
