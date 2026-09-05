<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Post;
use App\Jobs\PublishScheduledPostJob;

class PublishDuePosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:publish-due';
    protected $description = 'Publish scheduled posts that are due';

    public function handle()
    {
        $this->info("Checking for due posts...");
        
        $posts = Post::where('status', Post::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($posts->isEmpty()) {
            $this->info("No posts to publish.");
            return;
        }

        foreach ($posts as $post) {
            $updated = Post::where('id', $post->id)
                ->where('status', Post::STATUS_SCHEDULED)
                ->update(['status' => Post::STATUS_PUBLISHING]);

            if ($updated) {
                PublishScheduledPostJob::dispatch($post->fresh());
                $this->info("Dispatched post {$post->id} for publishing.");
            }
        }
    }
}
