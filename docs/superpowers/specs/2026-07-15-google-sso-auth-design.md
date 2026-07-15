# Google SSO Authentication Design Spec

**Date:** 2026-07-15  
**Approach:** Clean Cutover (Approach A) — Google SSO primary, email/password fallback  
**OAuth Flow:** Laravel Socialite (backend-driven) with new-tab + `postMessage` pattern

---

## 1. Overview

Replace the current email/password-first authentication flow with Google Workspace SSO as the primary login method. Email/password remains available as a de-emphasized fallback. Existing users are linked to their Google accounts by email match on first SSO login.

### Goals

- Company users authenticate via Google Workspace (single sign-on)
- Domain restriction enforced via existing `DomainValidationService`
- Existing user accounts, projects, teams, and data preserved
- Admin provisioning via CLI (`make:admin`) and admin panel invite
- Email/password retained as fallback, untouched

### Non-Goals

- Multi-provider OAuth abstraction (no provider table, no abstraction layer)
- Google Workspace directory sync or group-based role mapping
- Changes to Sanctum token mechanics
- Changes to team/project/feature logic

---

## 2. Backend — Database & Configuration

### Migration

Add `google_id` column to `users` table:

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('google_id')->nullable()->unique()->after('id');
    $table->string('password')->nullable()->change();
});
```

- `google_id` is nullable (existing users have no Google link yet)
- `password` becomes nullable (Google SSO users have no password)
- Existing users retain their data; `google_id` is populated on first SSO login

### Environment Variables

```
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=${APP_URL}/api/auth/google/callback
GOOGLE_HD_DOMAIN=yourcompany.com
```

### Socialite Configuration

In `config/services.php`:

```php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
],
```

### User Model Changes

Add `google_id` to `$fillable` in `app/Models/User.php`:

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
    'google_id',  // new
];
```

Note: The existing `'password' => 'hashed'` cast in Laravel 11 correctly passes through `null` values without hashing — no change needed to the cast.

### Package

```
composer require laravel/socialite
```

---

## 3. Backend — OAuth Routes & Callback Logic

### New Routes

In `routes/api.php`, add before the `auth:sanctum` groups:

```php
Route::prefix('auth/google')->group(function () {
    Route::get('/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('/callback', [GoogleAuthController::class, 'callback'])
        ->middleware('throttle:10,1');
});
```

These routes are public (no `auth:sanctum` or `verified` middleware). Callback is rate-limited to prevent abuse.

### GoogleAuthController

**`redirect` method:**

- Initiates Socialite redirect to Google
- Passes `hd` (hosted domain) parameter to restrict to company Workspace org
- Example: `Socialite::driver('google')->with(['hd' => env('GOOGLE_HD_DOMAIN')])->redirect()`

**`callback` method:**

1. Retrieve Google user: `$googleUser = Socialite::driver('google')->user()`
2. **Guard against null email:** If `$googleUser->getEmail()` is null, reject with error `"Google account has no email address"`
3. Validate domain allowlist via `DomainValidationService::isDomainAllowed($googleUser->getEmail())` — reject with 403 if not allowed
4. **Find existing user by email:**
   - If found AND already has a different `google_id`: reject with error `"This Google account is linked to another user"`
   - If found AND has no `google_id` (or matching `google_id`): link `google_id`, ensure `email_verified_at` is set, return Sanctum token
   - If not found: create new user with `google_id`, `email_verified_at = now()`, default role `member`, `password = null`
5. Render `google-callback.blade.php` with token + user data (or error). User object uses the existing `userShape()` format: `{ id, name, email, role, email_verified_at }`

### Callback Blade View (`google-callback.blade.php`)

Handles both popup/new-tab and full-page fallback scenarios:

```blade
<script>
  (function() {
    var payload = @json($payload);
    var origin = '{{ config("app.url") }}';
    
    if (window.opener) {
      // Popup or new-tab mode: post message back to opener
      window.opener.postMessage(payload, origin);
      window.close();
    } else {
      // Full-page fallback mode: redirect to frontend with token in URL fragment
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
```

On success, `$payload` = `{ token, user }`.  
On error, `$payload` = `{ error: "message" }`.

**Full-page fallback:** A new route `/auth/callback` on the frontend reads the URL fragment (`#token=...&user=...`), stores token/user in localStorage, and redirects to `/dashboard`. URL fragments are never sent to the server (not in logs, not in URLs).

### Error Cases

| Scenario | Error Message |
|---|---|
| Domain not allowed | `"This email domain is not authorized"` |
| Google account linked to different user | `"This Google account is linked to another user"` |
| Google returns no email | `"Google account has no email address"` |
| Socialite fails (user denied/cancelled) | `"Authentication cancelled"` |
| Unexpected error | `"Authentication failed. Please try again."` |

---

## 4. Backend — Admin Provisioning

### CLI (`make:admin`) — No Changes

Existing command creates user with `role: 'admin'` and `email_verified_at` set. First Google login links by email match.

### Admin Panel Invite (New)

New admin route: `POST /admin/users/invite`

- Placed inside the existing admin middleware group: `['auth:sanctum', 'can:admin-access']` with prefix `admin` (same as other admin routes in `api.php`)
- Accepts: `{ email: string, role?: string }`
- Creates user with `password: null`, `email_verified_at: now()`, `role: 'member'` (or specified role)
- If user with that email already exists, return 422
- Invited user's first Google login links their `google_id`

### SSO-Linked Users and Email Changes

The existing `AuthController::changeEmail` method allows authenticated users to change their email. For SSO-linked users, this creates a mismatch: their account email no longer matches their Google email. On next SSO login, the email-match linking won't find them.

**Resolution:** Prevent email changes for SSO-linked users. If `$user->google_id` is set, return 422 with message `"Cannot change email for accounts linked to Google SSO. Contact an administrator."`

---

## 5. Frontend — Login Page & Auth Context

### Login Page (`app/(auth)/login/page.tsx`)

Redesigned layout:

1. **"Sign in with Google" button** — primary CTA, full-width, Google branding
2. **Divider** — "or continue with email"
3. **Email/password form** — existing form below divider, de-emphasized

Clicking "Sign in with Google" calls `loginWithGoogle()` from the auth context.

### New-Tab Auth Flow (`useGoogleAuth` hook)

```tsx
async function openGoogleAuthTab() {
  const authTab = window.open('/api/auth/google/redirect', '_blank')
  
  if (!authTab) {
    // Popup blocked — full page fallback
    // The callback Blade view detects no window.opener and redirects
    // to /auth/callback#token=... which the frontend handles
    window.location.href = '/api/auth/google/redirect'
    return
  }
  
  const handler = async (event: MessageEvent) => {
    if (event.origin !== window.location.origin) return  // security
    
    window.removeEventListener('message', handler)
    clearTimeout(timeout)
    
    if (event.data.error) {
      setError(event.data.error)
      return
    }
    
    localStorage.setItem('token', event.data.token)
    localStorage.setItem('user', JSON.stringify(event.data.user))
    setToken(event.data.token)
    setUser(event.data.user)
    await refreshUser()  // load settings (creation_disabled, etc.)
    router.push('/dashboard')
  }
  
  // Cleanup listener if tab is closed manually or OAuth hangs
  const timeout = setTimeout(() => {
    window.removeEventListener('message', handler)
  }, 5 * 60 * 1000)  // 5 minutes
  
  window.addEventListener('message', handler)
}
```

Note: `refreshUser()` is called after SSO login (same as password login) to load user settings.

### AuthProvider Changes

Add `loginWithGoogle()` method to context (wraps the `openGoogleAuthTab` logic above). Add a new `/auth/callback` page that reads URL fragment params for the full-page fallback case:

```tsx
// app/(auth)/auth/callback/page.tsx
'use client'
import { useEffect } from 'react'
import { useRouter } from 'next/navigation'
import { useAuth } from '@/lib/auth'

export default function AuthCallbackPage() {
  const router = useRouter()
  const { setToken, setUser, refreshUser } = useAuth()

  useEffect(() => {
    const hash = window.location.hash.slice(1) // remove #
    const params = new URLSearchParams(hash)
    const token = params.get('token')
    const user = params.get('user')

    if (token && user) {
      localStorage.setItem('token', token)
      localStorage.setItem('user', user)
      setToken(token)
      setUser(JSON.parse(user))
      refreshUser().then(() => router.push('/dashboard'))
    } else {
      router.push('/login?error=' + (params.get('error') || 'Authentication failed'))
    }
  }, [])

  return <p>Completing sign-in...</p>
}
```

Note: URL fragments (`#...`) are never sent to the server — they exist only in the browser. Existing `login()` and `register()` methods unchanged for fallback path.

### Registration Page (`app/(auth)/register/page.tsx`)

Replaced with:

1. **"Sign up with Google" button** — primary CTA (same new-tab flow)
2. **Divider** — "or register with email"
3. **Existing registration form** — below divider as fallback
4. Google SSO users are auto-verified (no email verification flow needed)

### Email Verification Pages

Routes remain functional for password-registered fallback users. Removed from navigation/UI — no longer linked from login or registration pages.

---

## 6. Testing Strategy

### Feature Tests

| Test | Coverage |
|---|---|
| `test_google_callback_creates_new_user` | New user created with `google_id` + `email_verified_at` |
| `test_google_callback_links_existing_user_by_email` | Existing user gets `google_id` linked, data preserved |
| `test_google_callback_rejects_domain_not_allowed` | DomainValidationService blocks non-company emails |
| `test_google_callback_rejects_duplicate_google_id` | Same Google account can't link to two users |
| `test_google_callback_rejects_null_email` | Reject when Google returns no email |
| `test_google_redirect_returns_socialite_redirect` | Redirect route hits Google OAuth |
| `test_admin_invite_creates_user_without_password` | Admin invite creates user with null password |
| `test_admin_invite_rejects_duplicate_email` | Admin invite returns 422 for existing email |
| `test_password_login_still_works` | Existing email/password flow unchanged |
| `test_sso_user_cannot_change_email` | SSO-linked user gets 422 on email change attempt |

---

## 7. Migration Path for Existing Users

1. Deploy backend changes (migration + Socialite + new routes)
2. Deploy frontend changes (login page redesign)
3. Existing users continue to log in with email/password (fallback path)
4. On first Google SSO login, their account is linked by email match
5. No user action required — seamless transition

---

## 8. Security Considerations

- **Domain validation:** `DomainValidationService` runs on every Google callback — this is the **real** security enforcement for domain restriction
- **`postMessage` origin check:** Frontend validates `event.origin === window.location.origin` before accepting tokens
- **`hd` parameter:** Passed to Google as a UX hint for the account picker — **this is NOT a security control**. It only pre-selects the Workspace domain in Google's UI. A user with a personal `@gmail.com` account can still complete the OAuth flow. Domain enforcement is server-side only via `DomainValidationService`.
- **`google_id` uniqueness:** Database-level unique constraint prevents duplicate linking
- **Null email guard:** Callback rejects Google accounts with no email address before any DB operations
- **Password field nullable:** Google SSO users have no password; `Auth::attempt()` fails gracefully for these accounts. The `'password' => 'hashed'` cast passes through null correctly.
- **State parameter:** Socialite handles CSRF state validation automatically
- **Token delivery:** Sanctum token is sent via `postMessage` to same-origin only — never logged or exposed in URLs. Full-page fallback uses URL fragments (`#token=...`) which are not sent to the server.
- **SSO email lock:** SSO-linked users cannot change their email (prevents orphaned Google accounts)
