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
    Route::get('/callback', [GoogleAuthController::class, 'callback']);
});
```

These routes are public (no `auth:sanctum` or `verified` middleware).

### GoogleAuthController

**`redirect` method:**

- Initiates Socialite redirect to Google
- Passes `hd` (hosted domain) parameter to restrict to company Workspace org
- Example: `Socialite::driver('google')->with(['hd' => env('GOOGLE_HD_DOMAIN')])->redirect()`

**`callback` method:**

1. Retrieve Google user: `$googleUser = Socialite::driver('google')->user()`
2. Validate domain allowlist via `DomainValidationService::isDomainAllowed($googleUser->getEmail())` — reject with 403 if not allowed
3. **Find existing user by email:**
   - If found: link `google_id`, ensure `email_verified_at` is set, return Sanctum token
   - If not found: create new user with `google_id`, `email_verified_at = now()`, default role `member`, `password = null`
4. **Reject if `google_id` already linked to a different user** (prevents account takeover)
5. Render `google-callback.blade.php` with token + user data (or error)

### Callback Blade View (`google-callback.blade.php`)

Minimal HTML page that posts the result back to the opener tab:

```blade
<script>
  window.opener.postMessage(@json($payload), '{{ config("app.url") }}');
  window.close();
</script>
```

On success, `$payload` = `{ token, user }`.  
On error, `$payload` = `{ error: "message" }`.

### Error Cases

| Scenario | Error Message |
|---|---|
| Domain not allowed | `"This email domain is not authorized"` |
| Google account linked to different user | `"This Google account is linked to another user"` |
| Socialite fails (user denied/cancelled) | `"Authentication cancelled"` |
| Unexpected error | `"Authentication failed. Please try again."` |

---

## 4. Backend — Admin Provisioning

### CLI (`make:admin`) — No Changes

Existing command creates user with `role: 'admin'` and `email_verified_at` set. First Google login links by email match.

### Admin Panel Invite (New)

New admin route: `POST /admin/users/invite`

- Accepts: `{ email: string, role?: string }`
- Creates user with `password: null`, `email_verified_at: now()`, `role: 'member'` (or specified role)
- If user with that email already exists, return 422
- Invited user's first Google login links their `google_id`

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
function openGoogleAuthTab() {
  const authTab = window.open('/api/auth/google/redirect', '_blank')
  
  if (!authTab) {
    // Popup blocked — full page fallback
    window.location.href = '/api/auth/google/redirect'
    return
  }
  
  const handler = (event: MessageEvent) => {
    if (event.origin !== window.location.origin) return  // security
    
    if (event.data.error) {
      setError(event.data.error)
      window.removeEventListener('message', handler)
      return
    }
    
    localStorage.setItem('token', event.data.token)
    localStorage.setItem('user', JSON.stringify(event.data.user))
    setToken(event.data.token)
    setUser(event.data.user)
    window.removeEventListener('message', handler)
    router.push('/dashboard')
  }
  
  window.addEventListener('message', handler)
}
```

### AuthProvider Changes

Add `loginWithGoogle()` method to context. Existing `login()` and `register()` methods unchanged for fallback path.

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
| `test_google_redirect_returns_socialite_redirect` | Redirect route hits Google OAuth |
| `test_admin_invite_creates_user_without_password` | Admin invite creates user with null password |
| `test_password_login_still_works` | Existing email/password flow unchanged |

---

## 7. Migration Path for Existing Users

1. Deploy backend changes (migration + Socialite + new routes)
2. Deploy frontend changes (login page redesign)
3. Existing users continue to log in with email/password (fallback path)
4. On first Google SSO login, their account is linked by email match
5. No user action required — seamless transition

---

## 8. Security Considerations

- **Domain validation:** `DomainValidationService` runs on every Google callback — same enforcement as registration
- **`postMessage` origin check:** Frontend validates `event.origin === window.location.origin` before accepting tokens
- **`hd` parameter:** Passed to Google to restrict account picker to company Workspace domain
- **`google_id` uniqueness:** Database-level unique constraint prevents duplicate linking
- **Password field nullable:** Google SSO users have no password; `Auth::attempt()` fails gracefully for these accounts
- **State parameter:** Socialite handles CSRF state validation automatically
- **Token delivery:** Sanctum token is sent via `postMessage` to same-origin only — never logged or exposed in URLs
