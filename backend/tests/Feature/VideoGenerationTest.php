<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Post;
use App\Models\MediaAsset;
use App\Jobs\GenerateVideoDraftJob;
use App\Jobs\GenerateVideoFinalJob;
use Illuminate\Support\Facades\Queue;

class VideoGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_queue_video_draft()
    {
        Queue::fake();
        putenv('VIDEO_GENERATION_ENABLED=true');

        $post = Post::factory()->create();

        $response = $this->postJson("/api/posts/{$post->id}/video-draft", [
            'reference_assets' => []
        ]);

        $response->assertStatus(202);
        Queue::assertPushed(GenerateVideoDraftJob::class);
    }

    public function test_cannot_queue_video_draft_if_disabled()
    {
        Queue::fake();
        // Override env to false for this test
        // By default it might be false, but let's make sure
        putenv('VIDEO_GENERATION_ENABLED=false');

        // We need to re-resolve the controller or reset the app because the controller checks env() in constructor
        // Instead of testing this, we will just rely on the env check. 
        // Laravel's config caching might make this tricky in tests, so we'll just check if it fails with 403 when disabled.
        
        $post = Post::factory()->create();

        $response = $this->postJson("/api/posts/{$post->id}/video-draft", [
            'reference_assets' => []
        ]);

        $response->assertStatus(403);
    }

    public function test_can_queue_video_final_if_draft_ready()
    {
        Queue::fake();
        putenv('VIDEO_GENERATION_ENABLED=true');

        $post = Post::factory()->create();
        
        $mediaAsset = MediaAsset::create([
            'brand_id' => $post->brand_id,
            'workspace_id' => $post->workspace_id,
            'type' => MediaAsset::TYPE_VIDEO,
            'status' => MediaAsset::STATUS_VIDEO_DRAFT_READY,
            'disk' => 'public',
            'path' => 'draft.mp4',
            'original_name' => 'draft.mp4',
            'stored_name' => 'draft.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 0,
            'checksum' => md5(uniqid())
        ]);
        
        $post->media()->attach($mediaAsset->id, ['role' => 'video_draft']);

        $response = $this->postJson("/api/posts/{$post->id}/video-final", [
            'reference_assets' => []
        ]);

        $response->assertStatus(202);
        Queue::assertPushed(GenerateVideoFinalJob::class);
    }

    public function test_cannot_queue_video_final_without_draft()
    {
        Queue::fake();
        putenv('VIDEO_GENERATION_ENABLED=true');

        $post = Post::factory()->create();

        $response = $this->postJson("/api/posts/{$post->id}/video-final", [
            'reference_assets' => []
        ]);

        $response->assertStatus(400);
    }
}
