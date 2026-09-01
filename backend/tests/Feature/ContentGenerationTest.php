<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Config;

class ContentGenerationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('generate-content:' . request()->ip());
        Config::set('services.openai.api_key', 'test-key');
        Config::set('services.ai.fallback_enabled', false);
    }

    private function validPayload()
    {
        return [
            'topic' => 'Test Topic',
            'main_information' => 'Test Info',
            'objective' => 'sales',
            'tone' => 'friendly',
            'length' => 'short',
            'number_of_versions' => 1
        ];
    }

    // 1. Test chọn provider theo config (OpenAI)
    public function test_uses_openai_provider_when_configured()
    {
        Config::set('services.ai.provider', 'openai');

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['versions' => [['title'=>'T','content'=>'C','cta'=>'C','hashtags'=>['#H']]]])]]
                ],
                'usage' => ['total_tokens' => 100]
            ], 200)
        ]);

        $response = $this->postJson('/api/content/generate', $this->validPayload());
        $response->assertStatus(200);
        $this->assertEquals('openai', $response->json('data.metadata.provider'));
    }

    // 2. Test chọn provider theo config (Ollama)
    public function test_uses_ollama_provider_when_configured()
    {
        Config::set('services.ai.provider', 'ollama');

        Http::fake([
            '*localhost:11434*' => Http::response([
                'response' => json_encode(['versions' => [['title'=>'T','content'=>'C','cta'=>'C','hashtags'=>['#H']]]])
            ], 200)
        ]);

        $response = $this->postJson('/api/content/generate', $this->validPayload());
        $response->assertStatus(200);
        $this->assertEquals('ollama', $response->json('data.metadata.provider'));
    }

    // 3. Test OpenAI trả kết quả hợp lệ
    public function test_openai_returns_valid_json()
    {
        Config::set('services.ai.provider', 'openai');
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['versions' => [['title'=>'T','content'=>'C','cta'=>'C','hashtags'=>['#H']]]])]]
                ],
                'usage' => ['total_tokens' => 100]
            ], 200)
        ]);
        $response = $this->postJson('/api/content/generate', $this->validPayload());
        $response->assertStatus(200)->assertJsonPath('success', true);
    }

    // 4. Test API key sai (OpenAI)
    public function test_openai_unauthorized_key()
    {
        Config::set('services.ai.provider', 'openai');
        Http::fake([
            'api.openai.com/*' => Http::response('Unauthorized', 401)
        ]);
        $response = $this->postJson('/api/content/generate', $this->validPayload());
        $response->assertStatus(401)->assertJsonPath('error_code', 'VALIDATION_FAILED');
    }

    // 5. Test hết quota (OpenAI 429)
    public function test_openai_quota_exceeded()
    {
        Config::set('services.ai.provider', 'openai');
        Http::fake([
            'api.openai.com/*' => Http::response('Rate limit exceeded', 429)
        ]);
        $response = $this->postJson('/api/content/generate', $this->validPayload());
        $response->assertStatus(429)->assertJsonPath('error_code', 'CONTENT_GENERATION_FAILED');
    }

    // 6. Test timeout (OpenAI)
    public function test_openai_timeout()
    {
        Config::set('services.ai.provider', 'openai');
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Operation timed out');
        });
        $response = $this->postJson('/api/content/generate', $this->validPayload());
        $response->assertStatus(504)->assertJsonPath('error_code', 'OLLAMA_TIMEOUT');
    }

    // 7. Test JSON sai (OpenAI)
    public function test_openai_invalid_json()
    {
        Config::set('services.ai.provider', 'openai');
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'invalid json']]]
            ], 200)
        ]);
        $response = $this->postJson('/api/content/generate', $this->validPayload());
        $response->assertStatus(502)->assertJsonPath('error_code', 'INVALID_AI_RESPONSE');
    }

    // 8. Test fallback bị tắt
    public function test_fallback_disabled_stops_on_error()
    {
        Config::set('services.ai.provider', 'openai');
        Config::set('services.ai.fallback_enabled', false);
        
        Http::fake([
            'api.openai.com/*' => Http::response('Error', 500),
            '*localhost:11434*' => Http::response(['response' => json_encode(['versions' => [['title'=>'T','content'=>'C','cta'=>'C','hashtags'=>['#H']]]])], 200)
        ]);

        $response = $this->postJson('/api/content/generate', $this->validPayload());
        // Because fallback is false, it should return 500 from OpenAI
        $response->assertStatus(500);
        Http::assertSentCount(1); // Only OpenAI was called
    }

    // 9. Test fallback hoạt động khi được bật
    public function test_fallback_enabled_switches_to_ollama_on_error()
    {
        Config::set('services.ai.provider', 'openai');
        Config::set('services.ai.fallback_enabled', true);
        
        Http::fake([
            'api.openai.com/*' => Http::response('Error', 500),
            '*localhost:11434*' => Http::response(['response' => json_encode(['versions' => [['title'=>'OllamaTitle','content'=>'C','cta'=>'C','hashtags'=>['#H']]]])], 200)
        ]);

        $response = $this->postJson('/api/content/generate', $this->validPayload());
        
        $response->assertStatus(200);
        $this->assertEquals('ollama', $response->json('data.metadata.provider'));
        Http::assertSentCount(2); // Both were called
    }

    // Rate Limit Test
    public function test_rate_limit_works()
    {
        Config::set('services.ai.provider', 'openai');
        Http::fake([
            '*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['versions' => [['title'=>'T','content'=>'C','cta'=>'C','hashtags'=>['#H']]]])]]]
            ], 200)
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/content/generate', $this->validPayload());
        }

        $response = $this->postJson('/api/content/generate', $this->validPayload());
        $response->assertStatus(429)->assertJsonPath('error_code', 'RATE_LIMIT_EXCEEDED');
    }
}
