<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\Illuminate\Support\Facades\DB::table('jobs')->delete();
\App\Models\Post::where('id', 5)->update([
    'status' => 'scheduled', 
    'publish_error' => null,
    'scheduled_at' => now()->subMinutes(5)
]);
echo "Cleared jobs and reset post 5 to scheduled.\n";
