<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$p = App\Models\Post::with('media')->find(3);
echo json_encode(App\Http\Resources\PostResource::make($p)->resolve());
