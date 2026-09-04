<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Post;
use App\Models\Brand;
use App\Models\PostVersion;
use App\Models\PostActivityLog;
use Carbon\Carbon;

class PostWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->brand = Brand::create([
            'name' => 'Test Brand',
            'slug' => 'test-brand',
            'industry' => 'Gym',
        ]);
    }

    public function test_can_create_draft_and_it_creates_version_and_log()
    {
        $response = $this->postJson('/api/posts', [
            'brand_id' => $this->brand->id,
            'title' => 'Test Draft',
            'content' => 'Content here',
            'status' => 'draft',
            'source' => 'manual'
        ]);

        $response->assertStatus(201);
        
        $post = Post::first();
        $this->assertEquals('draft', $post->status);
        $this->assertEquals(1, $post->content_version);
        
        $this->assertDatabaseHas('post_versions', ['post_id' => $post->id, 'version_number' => 1]);
        $this->assertDatabaseHas('post_activity_logs', ['post_id' => $post->id, 'action' => 'created']);
    }

    public function test_updating_content_clears_quality_score()
    {
        $post = Post::create([
            'title' => 'Old Title',
            'content' => 'Old Content',
            'status' => 'draft',
            'quality_score' => 90,
            'quality_status' => 'passed',
            'content_version' => 1,
            'last_content_hash' => md5('Old Title|Old Content||null')
        ]);

        $response = $this->putJson('/api/posts/' . $post->id, [
            'title' => 'New Title',
            'content' => 'New Content',
            'status' => 'draft',
            'source' => 'manual'
        ]);

        $response->assertStatus(200);

        $post->refresh();
        $this->assertNull($post->quality_score);
        $this->assertEquals('unchecked', $post->quality_status);
        $this->assertEquals(2, $post->content_version);
    }

    public function test_updating_without_changing_content_does_not_increment_version()
    {
        $post = Post::create([
            'title' => 'Old Title',
            'content' => 'Old Content',
            'status' => 'draft',
            'quality_score' => 90,
            'quality_status' => 'passed',
            'content_version' => 1,
            'last_content_hash' => md5('Old Title|Old Content||null') // matches calculateHash logic somewhat
        ]);

        // Fix the hash accurately
        $app = app(\App\Services\PostWorkflowService::class);
        $post->last_content_hash = $app->calculateHash('Old Title', 'Old Content', null, []);
        $post->save();

        $response = $this->putJson('/api/posts/' . $post->id, [
            'title' => 'Old Title',
            'content' => 'Old Content',
            'status' => 'draft',
            'source' => 'manual'
        ]);

        $response->assertStatus(200);

        $post->refresh();
        $this->assertEquals(90, $post->quality_score);
        $this->assertEquals(1, $post->content_version);
    }

    public function test_can_submit_for_review_when_quality_passed()
    {
        $post = Post::create([
            'title' => 'Title',
            'content' => 'Content',
            'status' => 'draft',
            'quality_status' => 'passed',
            'quality_score' => 85,
        ]);

        $response = $this->postJson('/api/posts/' . $post->id . '/submit-review');
        
        $response->assertStatus(200);
        $post->refresh();
        $this->assertEquals('in_review', $post->status);
    }

    public function test_cannot_submit_for_review_when_quality_failed()
    {
        $post = Post::create([
            'title' => 'Title',
            'content' => 'Content',
            'status' => 'draft',
            'quality_status' => 'failed',
        ]);

        $response = $this->postJson('/api/posts/' . $post->id . '/submit-review');
        $response->assertStatus(422);
    }

    public function test_can_approve_in_review_post()
    {
        $post = Post::create([
            'title' => 'Title',
            'content' => 'Content',
            'status' => 'in_review',
        ]);

        $response = $this->postJson('/api/posts/' . $post->id . '/approve');
        $response->assertStatus(200);
        
        $post->refresh();
        $this->assertEquals('approved', $post->status);
    }

    public function test_can_request_changes_with_note()
    {
        $post = Post::create([
            'title' => 'Title',
            'content' => 'Content',
            'status' => 'in_review',
        ]);

        $response = $this->postJson('/api/posts/' . $post->id . '/request-changes', [
            'note' => 'Please add a CTA'
        ]);
        
        $response->assertStatus(200);
        $post->refresh();
        $this->assertEquals('changes_requested', $post->status);
        $this->assertEquals('Please add a CTA', $post->review_note);
    }

    public function test_cannot_request_changes_without_note()
    {
        $post = Post::create([
            'title' => 'Title',
            'content' => 'Content',
            'status' => 'in_review',
        ]);

        $response = $this->postJson('/api/posts/' . $post->id . '/request-changes');
        $response->assertStatus(422);
    }

    public function test_cannot_edit_post_in_review()
    {
        $post = Post::create([
            'title' => 'Title',
            'content' => 'Content',
            'status' => 'in_review',
        ]);

        $response = $this->putJson('/api/posts/' . $post->id, [
            'title' => 'New',
            'content' => 'New',
            'status' => 'in_review',
            'source' => 'manual'
        ]);
        
        $response->assertStatus(422);
    }

    public function test_mark_ready_fails_if_hash_changed()
    {
        $post = Post::create([
            'title' => 'Title',
            'content' => 'Content',
            'status' => 'approved',
            'last_content_hash' => 'old_hash'
        ]);

        $response = $this->postJson('/api/posts/' . $post->id . '/mark-ready');
        $response->assertStatus(422);
    }
}
