<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SkillUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_cannot_upload_private_skill(): void
    {
        $json = json_encode([
            'name' => 'Private Auditor',
            'role' => 'reviewer',
            'system_prompt' => 'Be mean.',
            'cost_multiplier' => 1.5,
        ]);

        $file = UploadedFile::fake()->createWithContent('skill.json', $json);

        $response = $this->actingAs($this->user)
            ->postJson('/api/user/skills/upload', [
                'file' => $file,
                'is_global' => false,
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('personas', [
            'name' => 'Private Auditor',
        ]);
    }

    public function test_user_cannot_upload_global_skill(): void
    {
        $json = json_encode([
            'name' => 'Global Expert',
            'role' => 'lead',
            'system_prompt' => 'Be helpful.',
        ]);

        $file = UploadedFile::fake()->createWithContent('skill.json', $json);

        $response = $this->actingAs($this->user)
            ->postJson('/api/user/skills/upload', [
                'file' => $file,
                'is_global' => true,
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('personas', [
            'name' => 'Global Expert',
        ]);
    }

    public function test_visibility_logic_sees_all_personas(): void
    {
        // 1. System default
        Persona::create(['name' => 'System', 'slug' => 'sys', 'role' => 'lead', 'system_prompt' => '...', 'is_global' => true]);
        
        // 2. Other user's global skill
        $otherUser = User::factory()->create();
        Persona::create(['name' => 'Other Global', 'slug' => 'og', 'role' => 'lead', 'system_prompt' => '...', 'user_id' => $otherUser->id, 'is_global' => true]);
        
        // 3. Other user's private skill (should NOW be visible)
        Persona::create(['name' => 'Other Private', 'slug' => 'op', 'role' => 'lead', 'system_prompt' => '...', 'user_id' => $otherUser->id, 'is_global' => false]);
        
        // 4. Current user's private skill
        Persona::create(['name' => 'My Private', 'slug' => 'mp', 'role' => 'lead', 'system_prompt' => '...', 'user_id' => $this->user->id, 'is_global' => false]);

        $response = $this->actingAs($this->user)->getJson('/api/personas');

        $response->assertOk()
            ->assertJsonCount(4) // All visible
            ->assertJsonFragment(['name' => 'System'])
            ->assertJsonFragment(['name' => 'Other Global'])
            ->assertJsonFragment(['name' => 'My Private'])
            ->assertJsonFragment(['name' => 'Other Private']);
    }

    public function test_user_skills_index_visibility_logic(): void
    {
        // 1. System default
        Persona::create(['name' => 'System', 'slug' => 'sys', 'role' => 'lead', 'system_prompt' => '...', 'is_global' => true]);

        // 2. Other user's global skill
        $otherUser = User::factory()->create();
        Persona::create(['name' => 'Other Global', 'slug' => 'og', 'role' => 'lead', 'system_prompt' => '...', 'user_id' => $otherUser->id, 'is_global' => true]);

        // 3. Other user's private skill (should NOW be visible)
        Persona::create(['name' => 'Other Private', 'slug' => 'op', 'role' => 'lead', 'system_prompt' => '...', 'user_id' => $otherUser->id, 'is_global' => false]);

        // 4. Current user's private skill
        Persona::create(['name' => 'My Private', 'slug' => 'mp', 'role' => 'lead', 'system_prompt' => '...', 'user_id' => $this->user->id, 'is_global' => false]);

        $response = $this->actingAs($this->user)->getJson('/api/user/skills');

        $response->assertOk()
            ->assertJsonCount(4, 'data') // All visible
            ->assertJsonFragment(['name' => 'System'])
            ->assertJsonFragment(['name' => 'Other Global'])
            ->assertJsonFragment(['name' => 'My Private'])
            ->assertJsonFragment(['name' => 'Other Private']);
    }

    public function test_user_cannot_delete_system_skills(): void
    {
        $systemSkill = Persona::create(['name' => 'System', 'slug' => 'sys', 'role' => 'lead', 'system_prompt' => '...', 'is_global' => true]);

        $this->actingAs($this->user)
            ->deleteJson("/api/user/skills/{$systemSkill->id}")
            ->assertForbidden();
    }

    public function test_user_cannot_delete_others_global_skills(): void
    {
        $otherUser = User::factory()->create();
        $otherSkill = Persona::create(['name' => 'Other', 'slug' => 'oth', 'role' => 'lead', 'system_prompt' => '...', 'user_id' => $otherUser->id, 'is_global' => true]);

        $this->actingAs($this->user)
            ->deleteJson("/api/user/skills/{$otherSkill->id}")
            ->assertForbidden();
    }

    public function test_user_cannot_delete_own_skills(): void
    {
        $mySkill = Persona::create(['name' => 'Mine', 'slug' => 'mine', 'role' => 'lead', 'system_prompt' => '...', 'user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->deleteJson("/api/user/skills/{$mySkill->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('personas', ['id' => $mySkill->id]);
    }
}
