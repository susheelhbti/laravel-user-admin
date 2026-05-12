<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Route registration
    |--------------------------------------------------------------------------
    */
    'register_routes'   => true,
    'route_prefix'      => 'api',
    'route_middleware'  => [],

    /*
    |--------------------------------------------------------------------------
    | OTP settings
    |--------------------------------------------------------------------------
    */
    'otp' => [
        'expires_in_minutes' => 5,
        'max_attempts'       => 3,
        'rate_limit_count'   => 3,
        'rate_limit_minutes' => 5,
        'code_length'        => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | User model + default roles
    |--------------------------------------------------------------------------
    */
    'user_model'        => \App\Models\User::class,
    'default_role_slug' => 'user',
    'admin_role_slug'   => 'admin',
    'token_name'        => 'auth_token',
    'auto_register'     => true,
    'per_page'          => 15,

    /*
    |--------------------------------------------------------------------------
    | Refresh token rotation
    |--------------------------------------------------------------------------
    */
    'refresh_token' => [
        'ttl_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Trusted device TTL (days)
    |--------------------------------------------------------------------------
    */
    'trusted_device_ttl_days' => 90,

    /*
    |--------------------------------------------------------------------------
    | Account deletion grace period
    |--------------------------------------------------------------------------
    */
    'account_deletion_grace_days' => 7,

    /*
    |--------------------------------------------------------------------------
    | TOTP 2FA  (requires: composer require pragmarx/google2fa)
    |--------------------------------------------------------------------------
    */
    'two_factor' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security questions
    |--------------------------------------------------------------------------
    */
    'security_questions' => [
        'required' => 2,
        'bank'     => [
            'What was the name of your first pet?',
            'What city were you born in?',
            'What was your childhood nickname?',
            'What is the name of your oldest sibling?',
            'What was the make of your first car?',
            "What is your mother's maiden name?",
            'What elementary school did you attend?',
            'What is the name of the street you grew up on?',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Geolocation & IP intelligence
    |
    | blocked_countries : ISO-3166-1 alpha-2 codes, e.g. ['CN','RU','KP']
    | anomaly_action    : 'warn' (fire event, allow login) | 'block'
    | anomaly_speed_kmh : threshold for impossible-travel detection
    |--------------------------------------------------------------------------
    */
    'geo' => [
        'blocked_countries'    => [],
        'check_ip_reputation'  => false,   // uses StopForumSpam API (free)
        'anomaly_detection'    => true,
        'anomaly_action'       => 'warn',
        'anomaly_speed_kmh'    => 900,
    ],

    /*
    |--------------------------------------------------------------------------
    | Conditional authentication rules
    |
    | Conditions: failed_logins | time_window | role | ip_country
    | Actions   : allow | require_2fa | require_captcha | block | suspend
    |--------------------------------------------------------------------------
    */
    'auth_rules' => [
        // ['condition' => ['type' => 'failed_logins', 'threshold' => 5], 'action' => 'require_captcha'],
        // ['condition' => ['type' => 'failed_logins', 'threshold' => 10], 'action' => 'suspend'],
        // ['condition' => ['type' => 'time_window', 'start' => '09:00', 'end' => '17:00', 'invert' => true], 'action' => 'block'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    */
    'webhooks' => [
        'enabled'      => true,
        'max_attempts' => 5,     // max delivery attempts with exponential backoff
    ],

    /*
    |--------------------------------------------------------------------------
    | User lifecycle
    |--------------------------------------------------------------------------
    */
    'lifecycle' => [
        'archive_after_inactive_days' => 180,
    ],

    /*
    |--------------------------------------------------------------------------
    | Profile completion score
    | Define fields and their point values (must sum to 100)
    |--------------------------------------------------------------------------
    */
    'profile_score' => [
        'fields' => [
            'name'     => 25,
            'email'    => 25,
            'phone'    => 20,
            'avatar'   => 15,
            'bio'      => 15,
        ],
    ],

];
