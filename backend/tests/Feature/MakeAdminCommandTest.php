<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MakeAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Laravel\Prompts\TextPrompt::fallbackWhen(true);
        \Laravel\Prompts\PasswordPrompt::fallbackWhen(true);
    }

    public function test_it_creates_an_admin_user()
    {
        $this->artisan('app:make-admin')
            ->expectsQuestion('What is the admin\'s name?', 'Admin User')
            ->expectsQuestion('What is the admin\'s email address?', 'admin@example.com')
            ->expectsQuestion('What should the password be?', 'password123')
            ->expectsOutputToContain('Admin user created successfully!')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);

        $user = User::where('email', 'admin@example.com')->first();
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_it_validates_unique_email()
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->artisan('app:make-admin')
            ->expectsQuestion('What is the admin\'s name?', 'Admin Two')
            ->expectsQuestion('What is the admin\'s email address?', 'existing@example.com')
            ->expectsOutputToContain('The email has already been taken.')
            ->assertExitCode(1);
    }
}
