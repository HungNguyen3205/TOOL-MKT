<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$accountId = env('CLOUDFLARE_ACCOUNT_ID');
$token = env('CLOUDFLARE_AI_TOKEN');
$model = '@cf/stabilityai/stable-diffusion-xl-base-1.0';

$response = Illuminate\Support\Facades\Http::withToken($token)
    ->post("https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/{$model}", [
        'prompt' => 'A cozy cinematic coffee shop interior at golden hour, 35mm, soft rim light'
    ]);

if ($response->successful()) {
    echo "SUCCESS\n";
    echo "Image size: " . strlen($response->body()) . " bytes\n";
} else {
    echo "FAILED\n";
    echo "Status: " . $response->status() . "\n";
    echo "Response: " . $response->body() . "\n";
}
