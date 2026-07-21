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

        $response = $this->get('/api/auth/google/callback?code=mock-code&state=test');

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

        $response = $this->get('/api/auth/google/callback?code=mock-code&state=test');

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

        $response = $this->get('/api/auth/google/callback?code=mock-code&state=test');

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

        $response = $this->get('/api/auth/google/callback?code=mock-code&state=test');

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

        $response = $this->get('/api/auth/google/callback?code=mock-code&state=test');

        $response->assertStatus(403);
    }

    public function test_google_redirect_returns_socialite_redirect(): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('with')->andReturnSelf();
        $driver->shouldReceive('redirect')->andReturn(
            redirect('https://accounts.google.com/o/oauth2/auth')
        );
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

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
