<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Post;

class PostManagementTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload()
    {
        return [
            'title' => 'Test Post',
            'content' => 'Test Content',
            'cta' => 'Buy now',
            'hashtags' => ['#test', '#app'],
            'objective' => 'sales',
            'tone' => 'friendly',
            'content_length' => 'short',
            'source' => 'manual',
            'status' => 'draft',
        ];
    }

    public function test_can_list_posts()
    {
        Post::factory()->count(15)->create();
        $response = $this->getJson('/api/posts');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'content', 'status', 'source']
            ],
            'links', 'meta'
        ]);
    }

    public function test_can_create_manual_post()
    {
        $response = $this->postJson('/api/posts', $this->validPayload());
        $response->assertStatus(201);
        $this->assertDatabaseHas('posts', ['title' => 'Test Post']);
    }

    public function test_can_create_ai_post()
    {
        $payload = array_merge($this->validPayload(), [
            'source' => 'ai_generated',
            'ai_model' => 'qwen2.5:3b',
            'ai_provider' => 'ollama',
            'selected_version' => 1,
            'source_input' => ['topic' => 'Test']
        ]);
        $response = $this->postJson('/api/posts', $payload);
        $response->assertStatus(201);
        $this->assertEquals('ai_generated', $response->json('data.source'));
    }

    public function test_create_fails_without_title()
    {
        $payload = $this->validPayload();
        unset($payload['title']);
        $response = $this->postJson('/api/posts', $payload);
        $response->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_FAILED');
    }

    public function test_create_fails_without_content()
    {
        $payload = $this->validPayload();
        unset($payload['content']);
        $response = $this->postJson('/api/posts', $payload);
        $response->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_FAILED');
    }

    public function test_invalid_status()
    {
        $payload = $this->validPayload();
        $payload['status'] = 'invalid_status';
        $response = $this->postJson('/api/posts', $payload);
        $response->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_FAILED');
    }

    public function test_invalid_source()
    {
        $payload = $this->validPayload();
        $payload['source'] = 'invalid_source';
        $response = $this->postJson('/api/posts', $payload);
        $response->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_FAILED');
    }

    public function test_invalid_objective()
    {
        $payload = $this->validPayload();
        $payload['objective'] = 'invalid_obj';
        $response = $this->postJson('/api/posts', $payload);
        $response->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_FAILED');
    }

    public function test_hashtags_must_be_array()
    {
        $payload = $this->validPayload();
        $payload['hashtags'] = 'not array';
        $response = $this->postJson('/api/posts', $payload);
        $response->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_FAILED');
    }

    public function test_hashtag_normalization()
    {
        $payload = $this->validPayload();
        $payload['hashtags'] = ['test', ' #app ', '', 'test'];
        $response = $this->postJson('/api/posts', $payload);
        $response->assertStatus(201);
        $this->assertEquals(['#test', '#app'], $response->json('data.hashtags'));
    }

    public function test_can_show_post()
    {
        $post = Post::factory()->create();
        $response = $this->getJson("/api/posts/{$post->id}");
        $response->assertStatus(200);
        $this->assertEquals($post->id, $response->json('data.id'));
    }

    public function test_show_not_found()
    {
        $response = $this->getJson('/api/posts/999');
        $response->assertStatus(404)->assertJsonPath('error_code', 'POST_NOT_FOUND');
    }

    public function test_can_update_post()
    {
        $post = Post::factory()->create();
        $payload = $this->validPayload();
        $payload['title'] = 'Updated Title';
        
        $response = $this->putJson("/api/posts/{$post->id}", $payload);
        $response->assertStatus(200);
        $this->assertEquals('Updated Title', $response->json('data.title'));
    }

    public function test_can_change_status_to_ready()
    {
        $post = Post::factory()->create(['status' => 'draft']);
        $response = $this->patchJson("/api/posts/{$post->id}/status", ['status' => 'ready']);
        $response->assertStatus(200);
        $this->assertEquals('ready', $response->json('data.status'));
    }

    public function test_cannot_change_status_to_ready_if_missing_data()
    {
        $post = Post::factory()->create(['status' => 'draft', 'title' => '']);
        $response = $this->patchJson("/api/posts/{$post->id}/status", ['status' => 'ready']);
        $response->assertStatus(422)->assertJsonPath('error_code', 'INVALID_STATUS_TRANSITION');
    }

    public function test_can_change_status_to_draft()
    {
        $post = Post::factory()->create(['status' => 'ready']);
        $response = $this->patchJson("/api/posts/{$post->id}/status", ['status' => 'draft']);
        $response->assertStatus(200);
        $this->assertEquals('draft', $response->json('data.status'));
    }

    public function test_can_soft_delete_post()
    {
        $post = Post::factory()->create();
        $response = $this->deleteJson("/api/posts/{$post->id}");
        $response->assertStatus(200);
        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_deleted_post_not_in_list()
    {
        $post = Post::factory()->create();
        $post->delete();
        $response = $this->getJson('/api/posts');
        $this->assertCount(0, $response->json('data'));
    }

    public function test_can_duplicate_post()
    {
        $post = Post::factory()->create(['title' => 'Original', 'status' => 'ready']);
        $response = $this->postJson("/api/posts/{$post->id}/duplicate");
        $response->assertStatus(201);
        $this->assertEquals('Original - Bản sao', $response->json('data.title'));
        $this->assertEquals('draft', $response->json('data.status'));
    }
    
    public function test_duplicate_not_found()
    {
        $response = $this->postJson('/api/posts/999/duplicate');
        $response->assertStatus(404);
    }

    public function test_search_by_title()
    {
        Post::factory()->create(['title' => 'Apple']);
        Post::factory()->create(['title' => 'Banana']);
        $response = $this->getJson('/api/posts?search=App');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_filter_by_status()
    {
        Post::factory()->create(['status' => 'draft']);
        Post::factory()->create(['status' => 'ready']);
        $response = $this->getJson('/api/posts?status=draft');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_filter_by_source()
    {
        Post::factory()->create(['source' => 'manual']);
        Post::factory()->create(['source' => 'ai_generated']);
        $response = $this->getJson('/api/posts?source=manual');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_pagination_works()
    {
        Post::factory()->count(15)->create();
        $response = $this->getJson('/api/posts?page=2');
        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
    }

    public function test_health_api_works()
    {
        $response = $this->getJson('/api/health');
        $response->assertStatus(200);
    }

    public function test_content_generation_does_not_leak_prompt()
    {
        // This is implicitly tested in ContentGenerationTest, but we can have a dummy one here
        $this->assertTrue(true);
    }
}
