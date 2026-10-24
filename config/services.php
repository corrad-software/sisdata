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

    // SRS-RBC-01 / SRS-RBC-03: the "SSO MAIS" OIDC identity provider
    // (Keycloak locally — see infra/keycloak/ and infra/bin/keycloak-dev.sh).
    // MFA is enforced by the IdP itself, not by this application.
    'keycloak' => [
        'client_id' => env('KEYCLOAK_CLIENT_ID', 'sisdata-web'),
        'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
        'redirect' => env('KEYCLOAK_REDIRECT_URI', rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/').'/auth/oidc/callback'),
        'base_url' => env('KEYCLOAK_BASE_URL', 'http://localhost:8180'),
        'realms' => env('KEYCLOAK_REALM', 'mais'),
        'after_login_redirect' => env('KEYCLOAK_AFTER_LOGIN_REDIRECT_URI', rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/').'/admin'),
        'login_page' => rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/').'/login',
        'post_logout_redirect' => env('KEYCLOAK_POST_LOGOUT_REDIRECT_URI', rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/').'/login'),
    ],

];
