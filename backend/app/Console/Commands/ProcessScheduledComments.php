<?php

namespace App\Console\Commands;

use App\Jobs\PublishFacebookComment;
use App\Models\ScheduledComment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledComments extends Command
{
    protected $signature = 'comments:process-scheduled {--limit=100}';
    protected $description = 'Dispatch due legacy scheduled comments safely';

    public function handle(): int
    {
        $limit = max(1, min((int) $this->option('limit'), 500));
        $dispatched = 0;

        ScheduledComment::query()
            ->where('status', 'queued')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->each(function (int $commentId) use (&$dispatched) {
                // Atomic claim: only one scheduler process may move this row forward.
                $claimed = ScheduledComment::query()
                    ->whereKey($commentId)
                    ->where('status', 'queued')
                    ->update([
                        'status' => 'processing',
                        'last_error' => null,
                    ]);

                if ($claimed !== 1) {
                    return;
                }

                try {
                    PublishFacebookComment::dispatch($commentId);
                    $dispatched++;
                } catch (\Throwable $exception) {
                    ScheduledComment::query()->whereKey($commentId)->update([
                        'status' => 'queued',
                        'last_error' => $exception->getMessage(),
                    ]);

                    Log::error('Could not dispatch scheduled comment.', [
                        'comment_id' => $commentId,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            });

        $this->info("Dispatched {$dispatched} due scheduled comments.");

        return self::SUCCESS;
    }
}
