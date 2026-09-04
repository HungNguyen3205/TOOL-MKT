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
        'scopes' => env('FACEBOOK_SCOPES', 'pages_show_list,pages_manage_posts,pages_read_engagement'),
        'frontend_redirect' => env('FACEBOOK_FRONTEND_REDIRECT_URL'),
        'timeout' => env('FACEBOOK_HTTP_TIMEOUT', 30),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'timeout' => env('OPENAI_TIMEOUT', 60),
        'max_output_tokens' => env('OPENAI_MAX_OUTPUT_TOKENS', 2500),
        'models_pricing' => [
            'gpt-4o-mini' => [
                'input' => 0.00015, // per 1k tokens
                'output' => 0.0006, // per 1k tokens
            ],
            'gpt-3.5-turbo' => [
                'input' => 0.0005,
                'output' => 0.0015,
            ]
        ],
    ],

    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_MODEL', 'qwen2.5:3b'),
        'timeout' => env('OLLAMA_TIMEOUT', 120),
    ],

    'ai_quality' => [
        'pass_score' => env('CONTENT_QUALITY_PASS_SCORE', 80),
        'warning_score' => env('CONTENT_QUALITY_WARNING_SCORE', 60),
        'similarity_threshold' => env('CONTENT_SIMILARITY_THRESHOLD', 0.85),
        'max_emoji' => env('CONTENT_MAX_EMOJI', 5),
    ],

    'ai' => [
        'provider' => env('AI_PROVIDER', 'openai'),
        'fallback_enabled' => env('AI_FALLBACK_ENABLED', false),
    ],
];
