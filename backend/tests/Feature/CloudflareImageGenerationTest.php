<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\Post;
use App\Models\MediaAsset;
use App\Services\ImageGeneration\CloudflareImageProvider;
use App\Jobs\GeneratePostImageJob;

class CloudflareImageGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.cloudflare_ai.account_id' => 'test_account_id',
            'services.cloudflare_ai.api_token' => 'test_token',
        ]);
    }

    public function test_provider_returns_success_and_base64_decode()
    {
        // 1 pixel transparent png base64
        $fakeBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
        
        Http::fake([
            'api.cloudflare.com/*' => Http::response([
                'success' => true,
                'result' => [
                    'image' => $fakeBase64
                ]
            ], 200)
        ]);

        $provider = new CloudflareImageProvider();
        $result = $provider->generate(['prompt' => 'test prompt']);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['image_data']);
    }

    public function test_provider_handles_rate_limit()
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response([], 429)
        ]);

        $provider = new CloudflareImageProvider();
        $result = $provider->generate(['prompt' => 'test prompt']);

        $this->assertFalse($result['success']);
        $this->assertEquals('CLOUDFLARE_RATE_LIMITED', $result['error_code']);
    }

    public function test_job_saves_media_asset_transactionally()
    {
        Storage::fake('public');
        
        // Setup post and media asset
        $post = Post::factory()->create([
            'status' => Post::STATUS_GENERATING_IMAGE
        ]);
        
        $mediaAsset = MediaAsset::create([
            'workspace_id' => 1,
            'type' => 'image',
            'status' => 'processing',
        ]);

        // Mock Provider
        $mockProvider = \Mockery::mock(CloudflareImageProvider::class);
        
        $fakePngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        
        $mockProvider->shouldReceive('generate')->once()->andReturn([
            'success' => true,
            'image_data' => $fakePngData,
            'provider' => 'cloudflare',
            'model' => 'test_model'
        ]);

        $job = new GeneratePostImageJob($post->id, $mediaAsset->id, 'test prompt');
        $job->handle($mockProvider);

        $mediaAsset->refresh();
        $post->refresh();

        $this->assertEquals('ready', $mediaAsset->status);
        $this->assertEquals('image/png', $mediaAsset->mime_type);
        $this->assertEquals(Post::STATUS_READY, $post->status);
        
        // Assert storage
        Storage::disk('public')->assertExists($mediaAsset->path);
        
        // Assert pivot relation
        $this->assertTrue($post->media()->wherePivot('role', 'primary')->where('media_assets.id', $mediaAsset->id)->exists());
    }
}
