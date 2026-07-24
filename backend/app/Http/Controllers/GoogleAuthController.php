<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DomainValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request)
    {
        $params = [];
        $hd = config('services.google.hd', env('GOOGLE_HD_DOMAIN'));
        if ($hd) {
            $params['hd'] = $hd;
        }

        return Socialite::driver('google')->with($params)->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            Log::warning('Google SSO callback error', ['error' => $e->getMessage()]);
            return $this->renderCallback(['error' => 'Authentication cancelled']);
        }

        $email = $googleUser->getEmail();

        if (empty($email)) {
            return $this->renderCallback(['error' => 'Google account has no email address'], 403);
        }

        $domainService = app(DomainValidationService::class);
        if (!$domainService->isDomainAllowed($email)) {
            return $this->renderCallback(['error' => 'This email domain is not authorized'], 403);
        }

        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            if ($existingUser->google_id && $existingUser->google_id !== $googleUser->getId()) {
                return $this->renderCallback(['error' => 'This Google account is linked to another user'], 403);
            }

            $existingUser->update([
                'google_id' => $googleUser->getId(),
                'email_verified_at' => $existingUser->email_verified_at ?? now(),
            ]);

            $user = $existingUser;
        } else {
            // Check google_id isn't already taken by a different email
            if (User::where('google_id', $googleUser->getId())->exists()) {
                return $this->renderCallback(['error' => 'This Google account is linked to another user'], 403);
            }

            $user = User::create([
                'name' => $googleUser->getName() ?: explode('@', $email)[0],
                'email' => $email,
                'google_id' => $googleUser->getId(),
                'email_verified_at' => now(),
                'password' => null,
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return $this->renderCallback([
            'token' => $token,
            'user' => $this->userShape($user),
        ]);
    }

    private function renderCallback(array $payload, int $status = 200)
    {
        return response()->view('google-callback', ['payload' => $payload], $status);
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
