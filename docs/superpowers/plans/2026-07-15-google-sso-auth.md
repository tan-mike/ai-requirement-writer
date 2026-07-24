# Google SSO Authentication Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Google Workspace SSO as the primary authentication method via Laravel Socialite with a new-tab + postMessage flow, keeping email/password as a fallback.

**Architecture:** Backend uses Laravel Socialite for the OAuth2 code exchange. The frontend opens a new tab to the backend's Socialite redirect URL. After Google authenticates the user, the backend callback renders a minimal Blade view that posts the Sanctum token back to the opener tab via `postMessage`, then closes itself. A full-page fallback uses URL fragments for popup-blocked scenarios.

**Tech Stack:** Laravel 11, Laravel Socialite, Next.js App Router, Sanctum, MySQL

**Spec:** `docs/superpowers/specs/2026-07-15-google-sso-auth-design.md`

---

## File Structure

### Backend — New Files
- `backend/app/Http/Controllers/GoogleAuthController.php` — OAuth redirect + callback logic
- `backend/resources/views/google-callback.blade.php` — Minimal JS view for postMessage/fragment handoff
- `backend/database/migrations/2026_07_15_000001_add_google_id_to_users_table.php` — Migration
- `backend/app/Http/Controllers/Admin/UserInviteController.php` — Admin invite endpoint
- `backend/tests/Feature/GoogleAuthTest.php` — Feature tests for SSO flow
- `backend/tests/Feature/AdminInviteTest.php` — Feature tests for admin invite

### Backend — Modified Files
- `backend/app/Models/User.php` — Add `google_id` to `$fillable`
- `backend/config/services.php` — Add Socialite OAuth keys under existing `google` config
- `backend/routes/api.php` — Add Google OAuth routes + admin invite route
- `backend/app/Http/Controllers/AuthController.php` — Block email change for SSO users
- `backend/.env.example` — Add `GOOGLE_HD_DOMAIN` and `GOOGLE_REDIRECT_URI`

### Frontend — New Files
- `frontend/app/(auth)/auth/callback/page.tsx` — Full-page fallback handler

### Frontend — Modified Files
- `frontend/lib/auth.tsx` — Add `loginWithGoogle()` to context
- `frontend/app/(auth)/login/page.tsx` — Add Google SSO button above email form
- `frontend/app/(auth)/register/page.tsx` — Add Google SSO button above registration form

---

### Task 1: Install Socialite & Add Migration

**Files:**
- Modify: `backend/composer.json` (via composer require)
- Create: `backend/database/migrations/2026_07_15_000001_add_google_id_to_users_table.php`
- Modify: `backend/app/Models/User.php:22-32`

- [ ] **Step 1: Install Laravel Socialite**

Run:
```bash
cd backend && composer require laravel/socialite
```

- [ ] **Step 2: Create the migration**

Create `backend/database/migrations/2026_07_15_000001_add_google_id_to_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('id');
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn('google_id');
            $table->string('password')->nullable(false)->change();
        });
    }
};
```

- [ ] **Step 3: Add `google_id` to User model `$fillable`**

In `backend/app/Models/User.php`, add `'google_id'` to the `$fillable` array after `'email_verified_at'`:

```php
protected $fillable = [
    'name',
    'email',
    'password',
    'role',
    'free_tier',
    'gemini_api_key',
    'tour_flags',
    'current_team_id',
    'email_verified_at',
    'google_id',
];
```

- [ ] **Step 4: Run migration to verify**

Run:
```bash
cd backend && php artisan migrate
```

Expected: Migration runs successfully.

- [ ] **Step 5: Commit**

```bash
git add backend/composer.json backend/composer.lock backend/database/migrations/2026_07_15_000001_add_google_id_to_users_table.php backend/app/Models/User.php
git commit -m "feat: add google_id column and install Socialite"
```

---

### Task 2: Configure Socialite & Environment Variables

**Files:**
- Modify: `backend/config/services.php:38-48`
- Modify: `backend/.env.example`

- [ ] **Step 1: Add Socialite OAuth config to services.php**

In `backend/config/services.php`, add `client_id`, `client_secret`, and `redirect` keys inside the existing `'google'` array (alongside the existing `'drive'` sub-array). Socialite reads `config('services.google.client_id')` etc., which won't conflict with the `drive` sub-array:

```php
'google' => [
    // OAuth (Socialite)
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),

    // Drive (existing)
    'drive' => [
        'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),
        'contexts_folder_id' => env('GOOGLE_DRIVE_CONTEXTS_FOLDER_ID'),
        'auth_method' => env('GOOGLE_AUTH_METHOD', 'service_account'),
        'service_account_json' => env('GOOGLE_SERVICE_ACCOUNT_JSON'),
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_REFRESH_TOKEN'),
    ],
],
```

- [ ] **Step 2: Add new env vars to .env.example**

Add `FRONTEND_URL` to `backend/.env.example` (it already exists — just add the Google-specific note):

```
GOOGLE_REDIRECT_URI=${APP_URL}/api/auth/google/callback
GOOGLE_HD_DOMAIN=
# Note: GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET are shared between Google Drive API and Google SSO.
# Ensure the OAuth client has the necessary credentials for both services.
```

- [ ] **Step 3: Commit**

```bash
git add backend/config/services.php backend/.env.example
git commit -m "feat: configure Socialite Google OAuth"
```

---

### Task 3: Write Google SSO Feature Tests (TDD)

**Files:**
- Create: `backend/tests/Feature/GoogleAuthTest.php`

- [ ] **Step 1: Write the failing tests**

Create `backend/tests/Feature/GoogleAuthTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private function mockSocialiteUser(string $email, string $id = 'google-123', ?string $name = 'Test User'): SocialiteUser
    {
        $user = Mockery::mock(SocialiteUser::class);
        $user->shouldReceive('getEmail')->andReturn($email);
        $user->shouldReceive('getId')->andReturn($id);
        $user->shouldReceive('getName')->andReturn($name);
        return $user;
    }

    public function test_google_callback_creates_new_user(): void
    {
        $socialiteUser = $this->mockSocialiteUser('newuser@company.com', 'google-new');

        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        $response = $this->get('/api/auth/google/callback');

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@company.com',
            'google_id' => 'google-new',
        ]);
        $user = User::where('email', 'newuser@company.com')->first();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);
    }

    public function test_google_callback_links_existing_user_by_email(): void
    {
        $existing = User::factory()->create(['email' => 'existing@company.com']);

        $socialiteUser = $this->mockSocialiteUser('existing@company.com', 'google-link');
        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        $response = $this->get('/api/auth/google/callback');

        $response->assertOk();
        $existing->refresh();
        $this->assertEquals('google-link', $existing->google_id);
        $this->assertNotNull($existing->email_verified_at);
    }

    public function test_google_callback_rejects_domain_not_allowed(): void
    {
        // Set domain restriction
        \App\Models\Setting::create(['key' => 'allowed_domains', 'value' => 'company.com']);

        $socialiteUser = $this->mockSocialiteUser('user@other.com', 'google-other');
        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        $response = $this->get('/api/auth/google/callback');

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'user@other.com']);
    }

    public function test_google_callback_rejects_duplicate_google_id(): void
    {
        User::factory()->create([
            'email' => 'first@company.com',
            'google_id' => 'google-dup',
        ]);

        // Second user tries to use same google_id but different email
        $socialiteUser = $this->mockSocialiteUser('second@company.com', 'google-dup');
        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        $response = $this->get('/api/auth/google/callback');

        // Should reject — google_id already taken by first@company.com
        $response->assertStatus(403);
    }

    public function test_google_callback_rejects_null_email(): void
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getEmail')->andReturn(null);
        $socialiteUser->shouldReceive('getId')->andReturn('google-noemail');
        $socialiteUser->shouldReceive('getName')->andReturn('No Email User');

        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        $response = $this->get('/api/auth/google/callback');

        $response->assertStatus(403);
    }

    public function test_google_redirect_returns_socialite_redirect(): void
    {
        Socialite::shouldReceive('driver->with->redirect')->andReturn(
            redirect('https://accounts.google.com/o/oauth2/auth')
        );

        $response = $this->get('/api/auth/google/redirect');

        $response->assertStatus(302);
    }

    public function test_sso_user_cannot_change_email(): void
    {
        $user = User::factory()->create([
            'email' => 'sso@company.com',
            'google_id' => 'google-sso',
        ]);

        $response = $this->actingAs($user)
            ->putJson('/api/auth/email/change', [
                'email' => 'newemail@company.com',
                'email_confirmation' => 'newemail@company.com',
            ]);

        $response->assertUnprocessable();
    }

    public function test_password_login_still_works(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['user', 'token']]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run:
```bash
cd backend && php artisan test --filter=GoogleAuthTest
```

Expected: All tests FAIL (controller doesn't exist yet, routes don't exist).

- [ ] **Step 3: Commit the failing tests**

```bash
git add backend/tests/Feature/GoogleAuthTest.php
git commit -m "test: add Google SSO feature tests (failing)"
```

---

### Task 4: Implement GoogleAuthController

**Files:**
- Create: `backend/app/Http/Controllers/GoogleAuthController.php`
- Create: `backend/resources/views/google-callback.blade.php`
- Modify: `backend/routes/api.php:1-11`

- [ ] **Step 1: Create the callback Blade view**

Create `backend/resources/views/google-callback.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head><title>Authenticating...</title></head>
<body>
<script>
  (function() {
    var payload = @json($payload);
    var origin = '{{ env("FRONTEND_URL", config("app.url")) }}';

    if (window.opener) {
      window.opener.postMessage(payload, origin);
      window.close();
    } else {
      if (payload.token) {
        window.location.replace(origin + '/auth/callback#token=' +
          encodeURIComponent(payload.token) + '&user=' +
          encodeURIComponent(JSON.stringify(payload.user)));
      } else {
        window.location.replace(origin + '/login?error=' +
          encodeURIComponent(payload.error || 'Authentication failed'));
      }
    }
  })();
</script>
</body>
</html>
```

- [ ] **Step 2: Create GoogleAuthController**

Create `backend/app/Http/Controllers/GoogleAuthController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DomainValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
```

- [ ] **Step 3: Add routes to api.php**

In `backend/routes/api.php`, add the Google OAuth routes after the existing `auth` prefix group (after line 22), and before the `auth:sanctum` group:

```php
Route::prefix('auth/google')->group(function () {
    Route::get('/redirect', [\App\Http\Controllers\GoogleAuthController::class, 'redirect']);
    Route::get('/callback', [\App\Http\Controllers\GoogleAuthController::class, 'callback'])
        ->middleware('throttle:10,1');
});
```

- [ ] **Step 4: Block email change for SSO users in AuthController**

In `backend/app/Http/Controllers/AuthController.php`, add a guard at the top of the `changeEmail` method (after `$user = $request->user();` on line 129):

```php
if ($user->google_id) {
    throw ValidationException::withMessages([
        'email' => ['Cannot change email for accounts linked to Google SSO. Contact an administrator.'],
    ]);
}
```

- [ ] **Step 5: Run tests**

Run:
```bash
cd backend && php artisan test --filter=GoogleAuthTest
```

Expected: All tests PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Http/Controllers/GoogleAuthController.php backend/resources/views/google-callback.blade.php backend/routes/api.php backend/app/Http/Controllers/AuthController.php
git commit -m "feat: implement Google SSO OAuth controller and routes"
```

---

### Task 5: Admin User Invite Endpoint

**Files:**
- Create: `backend/app/Http/Controllers/Admin/UserInviteController.php`
- Create: `backend/tests/Feature/AdminInviteTest.php`
- Modify: `backend/routes/api.php:98-104`

- [ ] **Step 1: Write the failing tests**

Create `backend/tests/Feature/AdminInviteTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_invite_creates_user_without_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->postJson('/api/admin/users/invite', [
                'email' => 'invited@company.com',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'email' => 'invited@company.com',
            'role' => 'member',
        ]);
        $user = User::where('email', 'invited@company.com')->first();
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_admin_invite_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@company.com']);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->postJson('/api/admin/users/invite', [
                'email' => 'existing@company.com',
            ]);

        $response->assertUnprocessable();
    }

    public function test_admin_invite_with_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->postJson('/api/admin/users/invite', [
                'email' => 'newadmin@company.com',
                'role' => 'admin',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'email' => 'newadmin@company.com',
            'role' => 'admin',
        ]);
    }

    public function test_non_admin_cannot_invite(): void
    {
        $member = User::factory()->create(['role' => 'member']);

        $response = $this->actingAs($member)
            ->postJson('/api/admin/users/invite', [
                'email' => 'invited@company.com',
            ]);

        $response->assertForbidden();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run:
```bash
cd backend && php artisan test --filter=AdminInviteTest
```

Expected: All tests FAIL.

- [ ] **Step 3: Create UserInviteController**

Create `backend/app/Http/Controllers/Admin/UserInviteController.php`:

```php
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
```

- [ ] **Step 4: Add route to api.php**

In `backend/routes/api.php`, add inside the existing admin middleware group (the `Route::middleware(['auth:sanctum', 'can:admin-access'])->prefix('admin')` block, after line 103):

```php
Route::post('/users/invite', \App\Http\Controllers\Admin\UserInviteController::class);
```

- [ ] **Step 5: Run tests**

Run:
```bash
cd backend && php artisan test --filter=AdminInviteTest
```

Expected: All tests PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Http/Controllers/Admin/UserInviteController.php backend/tests/Feature/AdminInviteTest.php backend/routes/api.php
git commit -m "feat: add admin user invite endpoint"
```

---

### Task 6: Frontend — AuthProvider Google SSO Method

**Files:**
- Modify: `frontend/lib/auth.tsx`

- [ ] **Step 1: Add `loginWithGoogle` to AuthContext interface**

In `frontend/lib/auth.tsx`, add `loginWithGoogle` to the `AuthContextType` interface (after `login` on line 19):

```tsx
loginWithGoogle: () => void
```

- [ ] **Step 2: Implement `loginWithGoogle` function inside AuthProvider**

Add this function inside the `AuthProvider` component (after the `login` function):

```tsx
function loginWithGoogle() {
    const authTab = window.open('/api/auth/google/redirect', '_blank')

    if (!authTab) {
      // Popup blocked — full page fallback
      window.location.href = '/api/auth/google/redirect'
      return
    }

    const handler = async (event: MessageEvent) => {
      if (event.origin !== window.location.origin) return

      window.removeEventListener('message', handler)
      clearTimeout(timeoutId)

      if (event.data.error) {
        setGoogleError(event.data.error)
        return
      }

      localStorage.setItem('token', event.data.token)
      localStorage.setItem('user', JSON.stringify(event.data.user))
      setToken(event.data.token)
      setUser(event.data.user)
      await refreshUser()
      window.location.href = '/dashboard'
    }

    const timeoutId = setTimeout(() => {
      window.removeEventListener('message', handler)
    }, 5 * 60 * 1000)

    window.addEventListener('message', handler)
  }
```

- [ ] **Step 3: Add `googleError` state and expose in context**

Add state inside `AuthProvider` (after `loading` state):

```tsx
const [googleError, setGoogleError] = useState<string | null>(null)
```

Update the context value to include `loginWithGoogle` and `googleError`:

```tsx
<AuthContext.Provider value={{
  user, token, settings,
  login, register, logout, refreshUser, switchTeam,
  resendVerification, verifyEmail,
  loginWithGoogle, googleError, setGoogleError,
  loading
}}>
```

Update the `AuthContextType` interface to include:

```tsx
googleError: string | null
setGoogleError: (error: string | null) => void
```

- [ ] **Step 4: Commit**

```bash
git add frontend/lib/auth.tsx
git commit -m "feat: add loginWithGoogle to AuthProvider"
```

---

### Task 7: Frontend — Login Page Google SSO Button

**Files:**
- Modify: `frontend/app/(auth)/login/page.tsx`

- [ ] **Step 1: Replace login page with Google SSO + email fallback**

Replace `frontend/app/(auth)/login/page.tsx` with:

```tsx
'use client'

import { useState } from 'react'
import { useRouter, useSearchParams } from 'next/navigation'
import Link from 'next/link'
import { useAuth } from '@/lib/auth'
import { Suspense } from 'react'

function LoginForm() {
  const { login, loginWithGoogle, googleError, setGoogleError } = useAuth()
  const router = useRouter()
  const searchParams = useSearchParams()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [submitting, setSubmitting] = useState(false)
  const verified = searchParams.get('verified') === '1'
  const externalError = searchParams.get('error')

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setGoogleError(null)
    const newErrors: Record<string, string> = {}
    if (!email) newErrors.email = 'Email is required'
    if (!password) newErrors.password = 'Password is required'
    if (Object.keys(newErrors).length) { setErrors(newErrors); return }

    setSubmitting(true)
    try {
      await login(email, password)
      router.push('/dashboard')
    } catch (err: unknown) {
      if (err && typeof err === 'object' && 'needsVerification' in err) {
        router.push('/check-email')
        return
      }
      setErrors({ general: err instanceof Error ? err.message : 'Login failed' })
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="card shadow-xl shadow-foreground/5 p-10">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-foreground">Sign in</h1>
        <p className="text-muted-foreground mt-1">Welcome back! Please enter your details.</p>
      </div>

      {(errors.general || googleError || externalError) && (
        <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md p-3 mb-6">
          <p className="text-red-700 dark:text-red-400 text-sm font-medium">{errors.general || googleError || externalError}</p>
        </div>
      )}

      {verified && (
        <div className="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md p-3 mb-6">
          <p className="text-green-700 dark:text-green-400 text-sm font-medium">
            Email verified successfully! You can now sign in.
          </p>
        </div>
      )}

      <button
        type="button"
        onClick={loginWithGoogle}
        className="w-full flex items-center justify-center gap-3 px-4 py-2.5 border border-border rounded-md hover:bg-muted transition-colors"
      >
        <svg className="w-5 h-5" viewBox="0 0 24 24">
          <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/>
          <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
          <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
          <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
        <span className="font-medium">Sign in with Google</span>
      </button>

      <div className="relative my-6">
        <div className="absolute inset-0 flex items-center">
          <div className="w-full border-t border-border"></div>
        </div>
        <div className="relative flex justify-center text-sm">
          <span className="px-2 text-muted-foreground bg-background">or continue with email</span>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="space-y-5">
        <div>
          <label htmlFor="email" className="block text-sm font-semibold text-foreground mb-1.5">Email</label>
          <input
            id="email" type="email" value={email}
            onChange={e => { setEmail(e.target.value); setGoogleError(null) }}
            className="input"
            placeholder="name@example.com"
          />
          {errors.email && <p className="text-red-600 dark:text-red-400 text-xs mt-1.5 font-medium">{errors.email}</p>}
        </div>
        <div>
          <div className="flex justify-between items-center mb-1.5">
            <label htmlFor="password" className="block text-sm font-semibold text-foreground">Password</label>
          </div>
          <input
            id="password" type="password" value={password}
            onChange={e => { setPassword(e.target.value); setGoogleError(null) }}
            className="input"
            placeholder="••••••••"
          />
          {errors.password && <p className="text-red-600 dark:text-red-400 text-xs mt-1.5 font-medium">{errors.password}</p>}
        </div>
        <button
          type="submit" disabled={submitting}
          className="btn-primary w-full py-2.5 mt-2"
        >
          {submitting ? 'Signing in...' : 'Sign in'}
        </button>
      </form>

      <div className="mt-8 pt-6 border-t border-border text-center">
        <p className="text-sm text-muted-foreground">
          Don&apos;t have an account?{' '}
          <Link href="/register" className="text-primary font-bold hover:underline underline-offset-4">
            Create an account
          </Link>
        </p>
      </div>
    </div>
  )
}

export default function LoginPage() {
  return (
    <Suspense>
      <LoginForm />
    </Suspense>
  )
}
```

- [ ] **Step 2: Commit**

```bash
git add frontend/app/\(auth\)/login/page.tsx
git commit -m "feat: add Google SSO button to login page"
```

---

### Task 8: Frontend — Register Page Google SSO Button

**Files:**
- Modify: `frontend/app/(auth)/register/page.tsx`

- [ ] **Step 1: Replace register page with Google SSO + email fallback**

Replace `frontend/app/(auth)/register/page.tsx` with:

```tsx
'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import Link from 'next/link'
import { useAuth } from '@/lib/auth'

export default function RegisterPage() {
  const { register, loginWithGoogle, googleError, setGoogleError } = useAuth()
  const router = useRouter()
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [submitting, setSubmitting] = useState(false)

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setGoogleError(null)
    const newErrors: Record<string, string> = {}
    if (!name) newErrors.name = 'Name is required'
    if (!email) newErrors.email = 'Email is required'
    if (password.length < 8) newErrors.password = 'Password must be at least 8 characters'
    if (Object.keys(newErrors).length) { setErrors(newErrors); return }

    setSubmitting(true)
    try {
      await register(name, email, password)
      router.push('/check-email')
    } catch (err) {
      setErrors({ general: err instanceof Error ? err.message : 'Registration failed' })
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="card shadow-xl shadow-foreground/5 p-10">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-foreground">Create account</h1>
        <p className="text-muted-foreground mt-1">Join us to start writing requirements efficiently.</p>
      </div>

      {(errors.general || googleError) && (
        <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md p-3 mb-6">
          <p className="text-red-700 dark:text-red-400 text-sm font-medium">{errors.general || googleError}</p>
        </div>
      )}

      <button
        type="button"
        onClick={loginWithGoogle}
        className="w-full flex items-center justify-center gap-3 px-4 py-2.5 border border-border rounded-md hover:bg-muted transition-colors"
      >
        <svg className="w-5 h-5" viewBox="0 0 24 24">
          <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/>
          <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
          <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
          <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
        <span className="font-medium">Sign up with Google</span>
      </button>

      <div className="relative my-6">
        <div className="absolute inset-0 flex items-center">
          <div className="w-full border-t border-border"></div>
        </div>
        <div className="relative flex justify-center text-sm">
          <span className="px-2 text-muted-foreground bg-background">or register with email</span>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="space-y-5">
        <div>
          <label htmlFor="name" className="block text-sm font-semibold text-foreground mb-1.5">Full Name</label>
          <input
            id="name" type="text" value={name}
            onChange={e => { setName(e.target.value); setGoogleError(null) }}
            className="input"
            placeholder="John Doe"
          />
          {errors.name && <p className="text-red-600 dark:text-red-400 text-xs mt-1.5 font-medium">{errors.name}</p>}
        </div>
        <div>
          <label htmlFor="email" className="block text-sm font-semibold text-foreground mb-1.5">Email</label>
          <input
            id="email" type="email" value={email}
            onChange={e => { setEmail(e.target.value); setGoogleError(null) }}
            className="input"
            placeholder="name@example.com"
          />
          {errors.email && <p className="text-red-600 dark:text-red-400 text-xs mt-1.5 font-medium">{errors.email}</p>}
        </div>
        <div>
          <label htmlFor="password" className="block text-sm font-semibold text-foreground mb-1.5">Password</label>
          <input
            id="password" type="password" value={password}
            onChange={e => { setPassword(e.target.value); setGoogleError(null) }}
            className="input"
            placeholder="••••••••"
          />
          {errors.password && <p className="text-red-600 dark:text-red-400 text-xs mt-1.5 font-medium">{errors.password}</p>}
        </div>
        <button
          type="submit" disabled={submitting}
          className="btn-primary w-full py-2.5 mt-2"
        >
          {submitting ? 'Creating account...' : 'Create account'}
        </button>
      </form>

      <div className="mt-8 pt-6 border-t border-border text-center">
        <p className="text-sm text-muted-foreground">
          Already have an account?{' '}
          <Link href="/login" className="text-primary font-bold hover:underline underline-offset-4">
            Sign in
          </Link>
        </p>
      </div>
    </div>
  )
}
```

- [ ] **Step 2: Commit**

```bash
git add frontend/app/\(auth\)/register/page.tsx
git commit -m "feat: add Google SSO button to register page"
```

---

### Task 9: Frontend — Auth Callback Page (Full-Page Fallback)

**Files:**
- Create: `frontend/app/(auth)/auth/callback/page.tsx`

- [ ] **Step 1: Create the callback page**

Create `frontend/app/(auth)/auth/callback/page.tsx`:

```tsx
'use client'

import { useEffect } from 'react'
import { useRouter } from 'next/navigation'
import { useAuth } from '@/lib/auth'

export default function AuthCallbackPage() {
  const router = useRouter()
  const { setToken, setUser, refreshUser } = useAuth()

  useEffect(() => {
    const hash = window.location.hash.slice(1)
    const params = new URLSearchParams(hash)
    const token = params.get('token')
    const userJson = params.get('user')
    const error = params.get('error')

    if (token && userJson) {
      localStorage.setItem('token', token)
      localStorage.setItem('user', userJson)
      setToken(token)
      setUser(JSON.parse(userJson))
      refreshUser().then(() => router.push('/dashboard'))
    } else {
      router.push('/login?error=' + (error || 'Authentication failed'))
    }
  }, [])

  return (
    <div className="card shadow-xl shadow-foreground/5 p-10 text-center">
      <p className="text-muted-foreground">Completing sign-in...</p>
    </div>
  )
}
```

- [ ] **Step 2: Commit**

```bash
git add frontend/app/\(auth\)/auth/callback/page.tsx
git commit -m "feat: add auth callback page for full-page fallback"
```

---

### Task 10: Run Full Test Suite & Final Verification

- [ ] **Step 1: Run all backend tests**

Run:
```bash
cd backend && php artisan test
```

Expected: All tests pass (existing + new).

- [ ] **Step 2: Verify frontend builds**

Run:
```bash
cd frontend && npm run build
```

Expected: Build succeeds with no errors.

- [ ] **Step 3: Final commit**

```bash
git add -A
git commit -m "feat: Google SSO authentication complete"
```
