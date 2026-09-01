<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use App\Models\Post;
use App\Models\FacebookPage;
use App\Models\PublicationLog;
use Carbon\Carbon;

class FacebookPublishTest extends TestCase
{
    use RefreshDatabase;

    protected $page;
    protected $post;

    protected function setUp(): void
    {
        parent::setUp();
        
        config(['services.facebook.app_id' => 'test_app_id']);
        
        $this->page = FacebookPage::create([
            'page_id' => '123456789',
            'page_name' => 'Test Page',
            'access_token' => 'test_token',
            'is_active' => true,
            'connection_status' => 'connected'
        ]);

        $this->post = Post::create([
            'title' => 'Test Title',
            'content' => 'Test Content',
            'status' => 'ready',
        ]);
    }

    public function test_publish_success()
    {
        Http::fake([
            'graph.facebook.com/*/123456789/feed*' => Http::response([
                'id' => '123456789_987654321'
            ], 200),
        ]);

        $response = $this->postJson("/api/posts/{$this->post->id}/publish", [
            'facebook_page_id' => $this->page->id,
            'confirmation' => true
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);

        $this->assertDatabaseHas('publication_logs', [
            'post_id' => $this->post->id,
            'facebook_page_id' => $this->page->id,
            'status' => 'success',
            'facebook_post_id' => '123456789_987654321'
        ]);

        $this->post->refresh();
        $this->assertEquals('success', $this->post->last_publication_status);
        $this->assertNotNull($this->post->published_at);
    }

    public function test_publish_fails_if_not_ready()
    {
        $draftPost = Post::create([
            'title' => 'Draft',
            'content' => 'Draft',
            'status' => 'draft',
        ]);

        $response = $this->postJson("/api/posts/{$draftPost->id}/publish", [
            'facebook_page_id' => $this->page->id,
            'confirmation' => true
        ]);

        $response->assertStatus(422)
                 ->assertJson(['error_code' => 'POST_NOT_READY']);
    }

    public function test_prevent_duplicate_concurrent_publish()
    {
        PublicationLog::create([
            'post_id' => $this->post->id,
            'facebook_page_id' => $this->page->id,
            'status' => 'processing',
            'request_type' => 'text',
            'attempted_at' => Carbon::now()
        ]);

        $response = $this->postJson("/api/posts/{$this->post->id}/publish", [
            'facebook_page_id' => $this->page->id,
            'confirmation' => true
        ]);

        $response->assertStatus(422)
                 ->assertJson(['error_code' => 'PUBLICATION_IN_PROGRESS']);
    }

    public function test_prevent_republish_already_published()
    {
        PublicationLog::create([
            'post_id' => $this->post->id,
            'facebook_page_id' => $this->page->id,
            'status' => 'success',
            'request_type' => 'text',
            'attempted_at' => Carbon::now()->subMinutes(10),
            'published_at' => Carbon::now()->subMinutes(10)
        ]);

        $response = $this->postJson("/api/posts/{$this->post->id}/publish", [
            'facebook_page_id' => $this->page->id,
            'confirmation' => true
        ]);

        $response->assertStatus(422)
                 ->assertJson(['error_code' => 'POST_ALREADY_PUBLISHED']);
    }
}
