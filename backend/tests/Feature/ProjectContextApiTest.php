<?php

namespace Tests\Feature;

use App\Models\ProjectContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectContextApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_all_contexts_globally()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        ProjectContext::create([
            'user_id' => $user1->id,
            'name' => 'Context 1',
            'content' => 'Content 1',
            'type' => 'file'
        ]);

        ProjectContext::create([
            'user_id' => $user2->id,
            'name' => 'Context 2',
            'content' => 'Content 2',
            'type' => 'file'
        ]);

        $response = $this->actingAs($user1)->getJson('/api/contexts');

        $response->assertStatus(200)
            ->assertJsonCount(2);
    }

    public function test_store_is_disabled()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/contexts', [
            'name' => 'New Context',
            'content' => 'Some content'
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Contexts are managed globally via Google Drive. Direct uploads are disabled.');
    }

    public function test_update_is_disabled()
    {
        $user = User::factory()->create();
        $context = ProjectContext::create([
            'user_id' => $user->id,
            'name' => 'Context',
            'content' => 'Content',
            'type' => 'file'
        ]);

        $response = $this->actingAs($user)->patchJson("/api/contexts/{$context->id}", [
            'content' => 'Updated content'
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Contexts are managed globally via Google Drive. Direct modifications are disabled.');
    }

    public function test_destroy_is_disabled()
    {
        $user = User::factory()->create();
        $context = ProjectContext::create([
            'user_id' => $user->id,
            'name' => 'Context',
            'content' => 'Content',
            'type' => 'file'
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/contexts/{$context->id}");

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Contexts are managed globally via Google Drive. Deletions are disabled.');
    }
}
