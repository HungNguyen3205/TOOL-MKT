<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Post;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function getStats(Request $request)
    {
        $workspaceId = $request->header('X-Workspace-ID', 1);

        $totalPages = DB::table('facebook_pages')->count();

        $queuedPosts = Post::where('status', 'scheduled')->count();

        $publishedToday = Post::where('status', 'published')
            ->whereDate('updated_at', Carbon::today())
            ->count();

        $failedPosts = Post::where('status', 'failed')->count();

        $activeCampaigns = DB::table('campaigns')
            ->where('workspace_id', $workspaceId)
            ->whereIn('status', ['running', 'draft'])
            ->count();

        // For activity, since we don't have campaigns yet, we will just return recent posts
        $recentActivity = Post::orderBy('updated_at', 'desc')
            ->limit(10)
            ->get(['id', 'title', 'status', 'updated_at']);

        $activity = $recentActivity->map(function($post) {
            return [
                'time' => $post->updated_at->format('H:i'),
                'name' => $post->title ?? 'Post #' . $post->id,
                'action' => ucfirst($post->status),
                'color' => $this->getStatusColor($post->status)
            ];
        });

        // Generate Chart Data (Last 7 Days)
        $chartData = [];
        $today = now();
        for ($i = 6; $i >= 0; $i--) {
            $date = $today->copy()->subDays($i);
            
            // Count successful posts for this day
            $successCount = Post::where('status', 'published')
                ->whereDate('updated_at', $date->toDateString())
                ->count();
                
            // Count failed posts for this day
            $failedCount = Post::where('status', 'failed')
                ->whereDate('updated_at', $date->toDateString())
                ->count();

            $chartData[] = [
                'name' => $date->format('D'), // Mon, Tue, etc.
                'success' => $successCount,
                'failed' => $failedCount
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_pages' => $totalPages,
                'queued_posts' => $queuedPosts,
                'published_today' => $publishedToday,
                'failed_posts' => $failedPosts,
                'active_campaigns' => $activeCampaigns,
                'activity' => $activity,
                'chart_data' => $chartData
            ]
        ]);
    }

    private function getStatusColor($status) {
        switch($status) {
            case 'published': return 'var(--dn-color-success)';
            case 'scheduled': return 'var(--dn-color-info)';
            case 'failed': return 'var(--dn-color-danger)';
            default: return 'var(--dn-color-warning)';
        }
    }
}
