<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\ContentTemplateController;
use App\Http\Controllers\FacebookAuthController;
use App\Http\Controllers\FacebookPageController;
use App\Http\Controllers\FacebookPublishController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('generate-content', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip())->response(function () {
        return response()->json([
            'success' => false,
            'message' => 'Bạn đã gửi quá nhiều yêu cầu. Vui lòng thử lại sau.',
            'error_code' => 'RATE_LIMIT_EXCEEDED'
        ], 429);
    });
});

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is running',
        'data' => [
            'application' => env('APP_NAME', 'AI Facebook Content Tool'),
            'environment' => env('APP_ENV', 'local'),
        ]
    ]);
});

Route::middleware('throttle:generate-content')->post('/content/generate', [\App\Http\Controllers\ContentController::class, 'generate']);

// Post API
Route::get('/posts', [\App\Http\Controllers\PostController::class, 'index']);
Route::post('/posts', [\App\Http\Controllers\PostController::class, 'store']);
Route::get('/posts/{id}', [\App\Http\Controllers\PostController::class, 'show']);
Route::put('/posts/{id}', [\App\Http\Controllers\PostController::class, 'update']);
Route::delete('/posts/{id}', [\App\Http\Controllers\PostController::class, 'destroy']);
Route::post('/posts/{id}/duplicate', [\App\Http\Controllers\PostController::class, 'duplicate']);
Route::post('/posts/{id}/quality-check', [\App\Http\Controllers\PostController::class, 'qualityCheck']);
Route::post('/posts/{id}/submit-review', [\App\Http\Controllers\PostController::class, 'submitReview']);
Route::post('/posts/{id}/approve', [\App\Http\Controllers\PostController::class, 'approve']);
Route::post('/posts/{id}/request-changes', [\App\Http\Controllers\PostController::class, 'requestChanges']);
Route::post('/posts/{id}/mark-ready', [\App\Http\Controllers\PostController::class, 'markReady']);
Route::post('/posts/{id}/return-to-draft', [\App\Http\Controllers\PostController::class, 'returnToDraft']);
Route::get('/posts/{id}/versions', [\App\Http\Controllers\PostController::class, 'versions']);
Route::post('/posts/{id}/versions/{versionId}/restore', [\App\Http\Controllers\PostController::class, 'restoreVersion']);
Route::get('/posts/{id}/activities', [\App\Http\Controllers\PostController::class, 'activities']);
// Brand API
Route::get('/brands/default', [\App\Http\Controllers\BrandController::class, 'getDefault']);
Route::get('/brands', [\App\Http\Controllers\BrandController::class, 'index']);
Route::post('/brands', [\App\Http\Controllers\BrandController::class, 'store']);
Route::get('/brands/{id}', [\App\Http\Controllers\BrandController::class, 'show']);
Route::put('/brands/{id}', [\App\Http\Controllers\BrandController::class, 'update']);
Route::patch('/brands/{id}', [\App\Http\Controllers\BrandController::class, 'update']); // Alias for update
Route::delete('/brands/{id}', [\App\Http\Controllers\BrandController::class, 'destroy']);
Route::patch('/brands/{id}/default', [\App\Http\Controllers\BrandController::class, 'setDefault']);
Route::patch('/brands/{id}/status', [\App\Http\Controllers\BrandController::class, 'setStatus']);
Route::get('/brands/{id}/versions', [\App\Http\Controllers\BrandController::class, 'versions']);
Route::post('/brands/{id}/versions/{versionId}/restore', [\App\Http\Controllers\BrandController::class, 'restoreVersion']);

// Brand Knowledge
Route::get('/brands/{brandId}/knowledge', [\App\Http\Controllers\BrandKnowledgeController::class, 'index']);
Route::post('/brands/{brandId}/knowledge', [\App\Http\Controllers\BrandKnowledgeController::class, 'store']);
Route::get('/brands/{brandId}/knowledge/{itemId}', [\App\Http\Controllers\BrandKnowledgeController::class, 'show']);
Route::put('/brands/{brandId}/knowledge/{itemId}', [\App\Http\Controllers\BrandKnowledgeController::class, 'update']);
Route::delete('/brands/{brandId}/knowledge/{itemId}', [\App\Http\Controllers\BrandKnowledgeController::class, 'destroy']);

// Brand Examples
Route::get('/brands/{brandId}/examples', [\App\Http\Controllers\BrandContentExampleController::class, 'index']);
Route::post('/brands/{brandId}/examples', [\App\Http\Controllers\BrandContentExampleController::class, 'store']);
Route::get('/brands/{brandId}/examples/{itemId}', [\App\Http\Controllers\BrandContentExampleController::class, 'show']);
Route::put('/brands/{brandId}/examples/{itemId}', [\App\Http\Controllers\BrandContentExampleController::class, 'update']);
Route::delete('/brands/{brandId}/examples/{itemId}', [\App\Http\Controllers\BrandContentExampleController::class, 'destroy']);

// Content Template API
Route::get('/brands/{brandId}/templates', [\App\Http\Controllers\ContentTemplateController::class, 'index']);
Route::post('/brands/{brandId}/templates', [\App\Http\Controllers\ContentTemplateController::class, 'store']);
Route::get('/brands/{brandId}/templates/{templateId}', [\App\Http\Controllers\ContentTemplateController::class, 'show']);
Route::put('/brands/{brandId}/templates/{templateId}', [\App\Http\Controllers\ContentTemplateController::class, 'update']);
Route::patch('/brands/{brandId}/templates/{templateId}', [\App\Http\Controllers\ContentTemplateController::class, 'update']);
Route::delete('/brands/{brandId}/templates/{templateId}', [\App\Http\Controllers\ContentTemplateController::class, 'destroy']);
Route::patch('/brands/{brandId}/templates/{templateId}/default', [\App\Http\Controllers\ContentTemplateController::class, 'setDefault']);
Route::patch('/brands/{brandId}/templates/{templateId}/status', [\App\Http\Controllers\ContentTemplateController::class, 'setStatus']);
Route::post('/brands/{brandId}/resolve-template', [\App\Http\Controllers\ContentTemplateController::class, 'resolveTemplate']);

// Facebook Routes
Route::prefix('facebook')->group(function () {
    Route::get('/auth-url', [FacebookAuthController::class, 'getAuthUrl']);
    Route::get('/callback', [FacebookAuthController::class, 'callback']);
    Route::get('/available-pages', [FacebookPageController::class, 'availablePages']);
    
    Route::prefix('pages')->group(function () {
        Route::get('/', [FacebookPageController::class, 'index']);
        Route::post('/connect', [FacebookPageController::class, 'connect']);
        Route::get('/{id}', [FacebookPageController::class, 'show']);
        Route::post('/{id}/verify', [FacebookPageController::class, 'verify']);
        Route::delete('/{id}', [FacebookPageController::class, 'destroy']);
    });
});

Route::post('/posts/{post}/publish', [\App\Http\Controllers\FacebookPublishController::class, 'publish']);
Route::get('/posts/{post}/publications', [\App\Http\Controllers\FacebookPublishController::class, 'history']);
Route::get('/publications', [\App\Http\Controllers\FacebookPublishController::class, 'allHistory']);
Route::post('/publications/{publication}/retry', [\App\Http\Controllers\FacebookPublishController::class, 'retry']);

Route::get('/settings', [\App\Http\Controllers\SettingsController::class, 'index']);
Route::post('/settings', [\App\Http\Controllers\SettingsController::class, 'update']);
