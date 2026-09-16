<?php
// Test script to run DashboardController logic and print any errors

use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

try {
    $workspaceId = 1;

    $totalPages = DB::table('facebook_pages')
        ->where('workspace_id', $workspaceId)
        ->count();

    $queuedPosts = Post::where('workspace_id', $workspaceId)
        ->where('status', 'scheduled')
        ->count();

    $publishedToday = Post::where('workspace_id', $workspaceId)
        ->where('status', 'published')
        ->whereDate('updated_at', Carbon::today())
        ->count();

    $failedPosts = Post::where('workspace_id', $workspaceId)
        ->where('status', 'failed')
        ->count();

    $activeCampaigns = DB::table('campaigns')
        ->where('workspace_id', $workspaceId)
        ->whereIn('status', ['running', 'draft'])
        ->count();

    $recentActivity = Post::where('workspace_id', $workspaceId)
        ->orderBy('updated_at', 'desc')
        ->limit(10)
        ->get(['id', 'title', 'status', 'updated_at']);

    // Generate Chart Data (Last 7 Days)
    $chartData = [];
    $today = now();
    for ($i = 6; $i >= 0; $i--) {
        $date = $today->copy()->subDays($i);
        
        $successCount = Post::where('workspace_id', $workspaceId)
            ->where('status', 'published')
            ->whereDate('updated_at', $date->toDateString())
            ->count();
            
        $failedCount = Post::where('workspace_id', $workspaceId)
            ->where('status', 'failed')
            ->whereDate('updated_at', $date->toDateString())
            ->count();

        $chartData[] = [
            'name' => $date->format('D'),
            'success' => $successCount,
            'failed' => $failedCount
        ];
    }

    echo "SUCCESS: \n";
    echo "Total Pages: $totalPages\n";
    echo "Active Campaigns: $activeCampaigns\n";
    echo "Failed Posts: $failedPosts\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
