<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

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

    'github' => [
        'app_id' => env('GITHUB_APP_ID'),
        'client_id' => env('GITHUB_APP_CLIENT_ID'),
        'client_secret' => env('GITHUB_APP_CLIENT_SECRET'),
        'private_key' => str_replace('\n', "\n", (string) env('GITHUB_APP_PRIVATE_KEY')),
        'webhook_secret' => env('GITHUB_APP_WEBHOOK_SECRET'),
        'slug' => env('GITHUB_APP_SLUG'),
    ],

    'slack_app' => [
        'client_id' => env('SLACK_APP_CLIENT_ID'),
        'client_secret' => env('SLACK_APP_CLIENT_SECRET'),
        'signing_secret' => env('SLACK_APP_SIGNING_SECRET'),
        'scopes' => env('SLACK_APP_SCOPES', 'chat:write,channels:read,groups:read'),
    ],

];
