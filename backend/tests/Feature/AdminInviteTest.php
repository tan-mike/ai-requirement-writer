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
