<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FacebookOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        config(['services.facebook.app_id' => 'test_app_id']);
        config(['services.facebook.app_secret' => 'test_app_secret']);
        config(['services.facebook.redirect' => 'http://localhost/callback']);
        config(['services.facebook.frontend_redirect' => 'http://localhost/frontend']);
    }

    public function test_get_auth_url_success()
    {
        $response = $this->getJson('/api/facebook/auth-url');

        $response->assertStatus(200)
                 ->assertJsonStructure(['success', 'data' => ['url']]);

        $url = $response->json('data.url');
        $this->assertStringContainsString('client_id=test_app_id', $url);
        $this->assertStringContainsString('state=', $url);
    }

    public function test_callback_missing_state()
    {
        $response = $this->get('/api/facebook/callback?code=test_code');
        
        $response->assertRedirect('http://localhost/frontend?status=error&error_code=FACEBOOK_OAUTH_INVALID');
    }

    public function test_callback_invalid_state()
    {
        $response = $this->get('/api/facebook/callback?code=test_code&state=invalid_state');
        
        $response->assertRedirect('http://localhost/frontend?status=error&error_code=FACEBOOK_OAUTH_STATE_INVALID');
    }

    public function test_callback_success()
    {
        Cache::put('fb_oauth_state_valid_state', true, now()->addMinutes(15));

        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response([
                'access_token' => 'user_test_token',
                'token_type' => 'bearer'
            ], 200),
            'graph.facebook.com/*/me/accounts*' => Http::response([
                'data' => [
                    [
                        'id' => '12345',
                        'name' => 'Test Page',
                        'access_token' => 'page_test_token'
                    ]
                ]
            ], 200),
        ]);

        $response = $this->get('/api/facebook/callback?code=test_code&state=valid_state');
        
        $response->assertRedirectContains('http://localhost/frontend?status=success&session_id=');
        
        // Assert state is removed (used once)
        $this->assertFalse(Cache::has('fb_oauth_state_valid_state'));
    }
}
