<?php

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'facebook' => [
        'app_id' => env('FACEBOOK_APP_ID'),
        'app_secret' => env('FACEBOOK_APP_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
        'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v20.0'),
        'scopes' => env('FACEBOOK_SCOPES', 'pages_show_list,pages_manage_posts,pages_read_engagement,pages_manage_engagement,pages_read_user_content'),
        'frontend_redirect' => env('FACEBOOK_FRONTEND_REDIRECT_URL'),
        'timeout' => (int) env('FACEBOOK_HTTP_TIMEOUT', 30),
        'queue' => env('FACEBOOK_PUBLISH_QUEUE', 'facebook-publish'),
    ],

    'google_drive' => [
        'enabled' => env('GOOGLE_DRIVE_ENABLED', false),
        'credentials_path' => env('GOOGLE_DRIVE_CREDENTIALS_PATH'),
        'timeout' => env('GOOGLE_DRIVE_TIMEOUT', 30),
    ],

    'ai_quality' => [
        'pass_score' => env('CONTENT_QUALITY_PASS_SCORE', 80),
        'warning_score' => env('CONTENT_QUALITY_WARNING_SCORE', 60),
        'similarity_threshold' => env('CONTENT_SIMILARITY_THRESHOLD', 0.85),
        'max_emoji' => env('CONTENT_MAX_EMOJI', 5),
    ],

    'ai' => [
        'text_provider' => env('AI_TEXT_PROVIDER', 'gemini'),
        'image_provider' => env('AI_IMAGE_PROVIDER', 'gemini'),
        'text_fallback_enabled' => env('AI_FALLBACK_ENABLED', false),
        'image_fallback_enabled' => env('IMAGE_FALLBACK_ENABLED', false),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'text_model' => env('GEMINI_TEXT_MODEL', 'gemini-3.5-flash-lite'),
        'image_model' => env('GEMINI_IMAGE_MODEL', 'imagen-3.0-generate-001'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout' => env('GEMINI_TIMEOUT', 60),
        'max_output_tokens' => env('GEMINI_MAX_OUTPUT_TOKENS', 800),
    ],

    'pollinations' => [
        'api_key' => env('POLLINATIONS_API_KEY'),
        'base_url' => env('POLLINATIONS_BASE_URL', 'https://gen.pollinations.ai'),
        'image_model' => env('POLLINATIONS_IMAGE_MODEL', 'flux'),
        'image_size' => env('POLLINATIONS_IMAGE_SIZE', '1024x1024'),
        'image_quality' => env('POLLINATIONS_IMAGE_QUALITY', 'low'),
        'timeout' => (int) env('POLLINATIONS_TIMEOUT', 120),
        'retry_times' => (int) env('IMAGE_RETRY_TIMES', 0),
    ],
];
