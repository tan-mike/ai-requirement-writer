<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use App\Notifications\VerifyEmailForSpa;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    // --- Registration ---

    public function test_register_does_not_return_token(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['message'])
            ->assertJsonMissingPath('data.token');

        Notification::assertSentTo(
            User::where('email', 'test@example.com')->first(),
            VerifyEmailForSpa::class
        );
    }

    public function test_register_rejects_non_allowed_domain(): void
    {
        Setting::create(['key' => 'allowed_domains', 'value' => 'company.com']);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@evil.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    // --- Login ---

    public function test_login_returns_409_for_unverified_user(): void
    {
        $user = User::factory()->unverified()->create([
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Please verify your email address before logging in.');
    }

    public function test_login_succeeds_for_verified_user(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['user', 'token']]);
    }

    // --- Email Verification ---

    public function test_verify_email_with_valid_signature(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // Extract params from signed URL
        $parsed = parse_url($url);
        parse_str($parsed['query'], $params);

        $response = $this->postJson('/api/auth/email/verify', $params);

        $response->assertOk();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_verify_email_rejects_invalid_signature(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->postJson('/api/auth/email/verify', [
            'id' => $user->id,
            'hash' => 'invalid-hash',
            'expires' => now()->addMinutes(60)->timestamp,
            'signature' => 'bad-signature',
        ]);

        $response->assertStatus(403);
    }

    public function test_verify_email_rejects_non_allowed_domain(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'user@evil.com']);
        Setting::create(['key' => 'allowed_domains', 'value' => 'company.com']);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $parsed = parse_url($url);
        parse_str($parsed['query'], $params);

        $response = $this->postJson('/api/auth/email/verify', $params);

        $response->assertUnprocessable();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    // --- Resend Verification ---

    public function test_resend_returns_200_regardless_of_email_existence(): void
    {
        // Non-existent email — still 200
        $response = $this->postJson('/api/auth/email/resend', [
            'email' => 'nobody@nowhere.com',
        ]);
        $response->assertOk();

        // Existing verified user — still 200 (no email sent)
        $user = User::factory()->create();
        $response = $this->postJson('/api/auth/email/resend', [
            'email' => $user->email,
        ]);
        $response->assertOk();
    }

    public function test_resend_sends_email_for_unverified_user(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $response = $this->postJson('/api/auth/email/resend', [
            'email' => $user->email,
        ]);

        $response->assertOk();
        Notification::assertSentTo($user, VerifyEmailForSpa::class);
    }

    // --- Email Change ---

    public function test_authenticated_user_can_change_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/auth/email/change', [
            'email' => 'newemail@example.com',
            'email_confirmation' => 'newemail@example.com',
        ]);

        $response->assertOk();
        $this->assertEquals('newemail@example.com', $user->fresh()->email);
        $this->assertNull($user->fresh()->email_verified_at);
        Notification::assertSentTo($user->fresh(), VerifyEmailForSpa::class);
    }

    public function test_email_change_rejects_non_allowed_domain(): void
    {
        $user = User::factory()->create();
        Setting::create(['key' => 'allowed_domains', 'value' => 'company.com']);

        $response = $this->actingAs($user)->putJson('/api/auth/email/change', [
            'email' => 'user@evil.com',
            'email_confirmation' => 'user@evil.com',
        ]);

        $response->assertUnprocessable();
    }
}
