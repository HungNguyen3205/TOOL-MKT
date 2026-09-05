<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use App\Models\FacebookPage;
use App\Models\Post;
use App\Models\Publication;
use App\Jobs\PublishFacebookTextPost;
use Tests\TestCase;

class FacebookIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock configurations
        config(['services.facebook.app_id' => 'real_app_id']);
        config(['services.facebook.app_secret' => 'real_app_secret']);
        config(['services.facebook.redirect' => 'http://localhost/callback']);
        config(['services.facebook.frontend_redirect' => 'http://localhost:5173/facebook-pages']);
    }

    public function test_oauth_state_invalid_returns_error()
    {
        $response = $this->get('/api/facebook/callback?code=mock_code&state=invalid_state');
        $response->assertRedirect('http://localhost:5173/facebook-pages?status=error&error_code=FACEBOOK_OAUTH_STATE_INVALID');
    }

    public function test_callback_denied_by_meta_returns_error()
    {
        $response = $this->get('/api/facebook/callback?error=access_denied&error_reason=user_denied&error_description=User+denied');
        $response->assertRedirect('http://localhost:5173/facebook-pages?status=error&error_code=FACEBOOK_OAUTH_DENIED&message=User+denied');
    }

    public function test_callback_exchanges_code_for_token_and_fetches_pages()
    {
        Cache::put('fb_oauth_state_valid_state', true, now()->addMinutes(15));

        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'user_token_123'], 200),
            'graph.facebook.com/*/me/accounts*' => Http::response([
                'data' => [
                    [
                        'id' => 'page_123',
                        'name' => 'Real Page',
                        'access_token' => 'page_token_123',
                        'tasks' => ['CREATE_CONTENT', 'MANAGE']
                    ]
                ]
            ], 200)
        ]);

        $response = $this->get('/api/facebook/callback?code=valid_code&state=valid_state');
        
        $response->assertStatus(302);
        $this->assertStringContainsString('session_id=', $response->headers->get('Location'));
    }

    public function test_callback_me_accounts_returns_empty()
    {
        Cache::put('fb_oauth_state_valid_state', true, now()->addMinutes(15));

        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'user_token_123'], 200),
            'graph.facebook.com/*/me/accounts*' => Http::response(['data' => []], 200)
        ]);

        $response = $this->get('/api/facebook/callback?code=valid_code&state=valid_state');
        $response->assertStatus(302);
        
        // Follow redirect to see pages
        $location = $response->headers->get('Location');
        parse_str(parse_url($location, PHP_URL_QUERY), $queries);
        
        $pagesRes = $this->get('/api/facebook/pages/available?session_id=' . $queries['session_id']);
        $pagesRes->assertStatus(200);
        $this->assertEmpty($pagesRes->json('data'));
    }

    public function test_verify_token_expired()
    {
        $page = FacebookPage::create([
            'page_id' => '123',
            'page_name' => 'Test',
            'access_token' => 'expired_token'
        ]);

        Http::fake([
            'graph.facebook.com/*/123*' => Http::response([
                'error' => ['message' => 'Session has expired']
            ], 401)
        ]);

        $response = $this->post("/api/facebook/pages/{$page->id}/verify");
        
        $response->assertStatus(400);
        $this->assertEquals('FACEBOOK_TOKEN_INVALID', $response->json('error_code'));
        
        $this->assertDatabaseHas('facebook_pages', [
            'id' => $page->id,
            'connection_status' => 'token_expired'
        ]);
    }

    public function test_verify_missing_permissions()
    {
        $page = FacebookPage::create([
            'page_id' => '123',
            'page_name' => 'Test',
            'access_token' => 'valid_token',
            'granted_scopes' => ['ANALYZE'] // Missing CREATE_CONTENT
        ]);

        Http::fake([
            'graph.facebook.com/*/123*' => Http::response([
                'id' => '123',
                'name' => 'Page',
                'permissions' => ['ANALYZE']
            ], 200)
        ]);

        $response = $this->post("/api/facebook/pages/{$page->id}/verify");
        
        $response->assertStatus(403);
        $this->assertEquals('FACEBOOK_PERMISSION_MISSING', $response->json('error_code'));
        
        $this->assertDatabaseHas('facebook_pages', [
            'id' => $page->id,
            'connection_status' => 'permission_missing'
        ]);
    }

    public function test_publish_post_success_from_queue()
    {
        Queue::fake();

        $page = FacebookPage::create(['page_id'=>'1','page_name'=>'A','access_token'=>'B','connection_status' => 'connected', 'is_active' => true]);
        $post = Post::create(['status' => 'ready', 'content_version' => 1, 'title' => 'Test']);

        Http::fake([
            'graph.facebook.com/*/*/verify*' => Http::response(['id' => $page->page_id, 'permissions' => ['CREATE_CONTENT']], 200),
            'graph.facebook.com/*/*/feed*' => Http::response(['id' => 'real_post_id_123'], 200),
        ]);

        // Publish request
        $response = $this->post("/api/facebook/publish/{$post->id}", [
            'facebook_page_id' => $page->id,
            'confirmation' => true
        ]);

        $response->assertStatus(200);
        Queue::assertPushed(PublishFacebookTextPost::class);

        // Simulate queue worker executing
        $publication = Publication::where('post_id', $post->id)->first();
        $job = new PublishFacebookTextPost($publication->id);
        app()->call([$job, 'handle']);

        $publication->refresh();
        $this->assertEquals('published', $publication->status);
        $this->assertEquals('real_post_id_123', $publication->external_post_id);
    }

    public function test_duplicate_publish_prevented()
    {
        $page = FacebookPage::create(['page_id'=>'1','page_name'=>'A','access_token'=>'B','connection_status' => 'connected', 'is_active' => true]);
        $post = Post::create(['status' => 'ready', 'title' => 'Test']);

        Publication::create([
            'post_id' => $post->id,
            'facebook_page_id' => $page->id,
            'status' => 'published',
            'publication_type' => 'text',
            'platform' => 'facebook',
            'idempotency_key' => 'test_idem_1'
        ]);

        $response = $this->post("/api/facebook/publish/{$post->id}", [
            'facebook_page_id' => $page->id,
            'confirmation' => true
        ]);

        $response->assertStatus(422);
        $this->assertEquals('POST_ALREADY_PUBLISHED', $response->json('error_code'));
    }

    public function test_publish_job_fails_and_retries()
    {
        $page = FacebookPage::create(['page_id'=>'1','page_name'=>'A','access_token'=>'B','connection_status' => 'connected', 'is_active' => true]);
        $post = Post::create(['status' => 'ready', 'content_version' => 1, 'title' => 'Test']);

        $publication = Publication::create([
            'post_id' => $post->id,
            'facebook_page_id' => $page->id,
            'status' => 'queued',
            'publication_type' => 'text',
            'platform' => 'facebook',
            'idempotency_key' => 'test_idem_retry',
            'content_snapshot' => ['title' => 'T', 'content' => 'C', 'hashtags' => [], 'cta' => 'CTA']
        ]);

        Http::fake([
            'graph.facebook.com/*/*/feed*' => Http::response(['error' => ['message' => 'User request limit reached']], 403),
        ]);

        $job = new PublishFacebookTextPost($publication->id);
        
        try {
            app()->call([$job, 'handle']);
        } catch (\Exception $e) {
            // expected to throw for retry
        }

        $publication->refresh();
        $this->assertEquals('queued', $publication->status); // Put back to queued for retry
        $this->assertEquals('FACEBOOK_RATE_LIMITED', $publication->last_error_code);
    }
}
