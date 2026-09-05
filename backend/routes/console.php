<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;
use App\Models\Post;
use App\Jobs\PublishScheduledPostJob;
use Carbon\Carbon;

Schedule::call(function () {
    $posts = Post::where('status', Post::STATUS_SCHEDULED)
        ->where('scheduled_at', '<=', Carbon::now())
        ->get();

    foreach ($posts as $post) {
        $updated = Post::where('id', $post->id)
            ->where('status', Post::STATUS_SCHEDULED)
            ->update(['status' => Post::STATUS_PUBLISHING]);

        if ($updated) {
            PublishScheduledPostJob::dispatch($post->fresh());
        }
    }
})->everyMinute();
