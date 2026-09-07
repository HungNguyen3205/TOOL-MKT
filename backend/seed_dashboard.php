<?php

use App\Models\Post;
use Illuminate\Support\Facades\DB;

// Disable foreign key checks for truncation
DB::statement('SET FOREIGN_KEY_CHECKS=0;');

// Clear existing posts
Post::truncate();
DB::table('campaigns')->truncate();

DB::statement('SET FOREIGN_KEY_CHECKS=1;');

echo "Generating mock data for dashboard...\n";

// Generate 50 posts spread across the last 7 days
for ($i = 0; $i < 50; $i++) {
    $date = now()->subDays(rand(0, 6));
    $status = (rand(0, 10) > 2) ? 'published' : 'failed';
    
    Post::create([
        'title' => 'Mock Post ' . $i,
        'content' => 'This is a mock post generated for the dashboard.',
        'status' => $status,
        'workspace_id' => 1,
        'brand_id' => null,
        'created_at' => $date,
        'updated_at' => $date,
    ]);
}

// Generate some mock campaigns
for ($i = 1; $i <= 3; $i++) {
    DB::table('campaigns')->insert([
        'name' => 'Chiến dịch Test ' . $i,
        'workspace_id' => 1,
        'status' => 'running',
        'type' => 'social_posting',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

echo "Seeding completed!\n";
