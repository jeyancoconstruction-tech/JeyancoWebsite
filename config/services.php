<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    // Google Maps JavaScript API (Places + Geocoding) — powers the site
    // location picker. Add GOOGLE_MAPS_API_KEY to your .env to enable it.
    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    // Sign in with Google (Laravel Socialite). Only a Google account whose
    // verified email is on an account an admin created gets in — nobody is
    // registered by signing in. Without both values the button is hidden.
    // The redirect must be listed, exactly, on the OAuth client in Google
    // Cloud: https://<site>/auth/google/callback.
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    // Google Calendar API — fills the Holidays tab from Google's public
    // "Holidays in Philippines" calendar. Without a key the tab keeps the
    // holidays it computes offline. See App\Support\GoogleHolidays.
    'google_calendar' => [
        'key'      => env('GOOGLE_CALENDAR_API_KEY'),
        'holidays' => env('GOOGLE_HOLIDAY_CALENDAR_ID', 'en.philippines#holiday@group.v.calendar.google.com'),
    ],

    // Anthropic (Claude) — powers the kiosk payroll assistant.
    // Always read via config('services.anthropic.key'), never env() directly,
    // so the value survives config caching in production.
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
    ],

    // Gemini API removed

];