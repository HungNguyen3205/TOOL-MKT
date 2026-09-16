<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ScheduledComment;
use App\Jobs\PublishFacebookComment;
use Carbon\Carbon;

class ProcessScheduledComments extends Command
{
    protected $signature = 'comments:process-scheduled';
    protected $description = 'Process scheduled comments that are due for publication';

    public function handle()
    {
        $now = Carbon::now();
        $this->info("Checking for scheduled comments due at or before {$now}...");

        $comments = ScheduledComment::where('status', 'queued')
            ->where('scheduled_at', '<=', $now)
            ->get();

        if ($comments->isEmpty()) {
            $this->info("No due scheduled comments found.");
            return;
        }

        foreach ($comments as $comment) {
            $this->info("Dispatching comment ID {$comment->id}...");
            PublishFacebookComment::dispatch($comment->id);
            $comment->update(['status' => 'processing']);
        }

        $this->info("Dispatched {$comments->count()} comments to queue.");
    }
}
