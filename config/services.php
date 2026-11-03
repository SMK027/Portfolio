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

    'recaptcha' => [
        'site_key'   => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
        'min_score'  => (float) env('RECAPTCHA_MIN_SCORE', 0.5),
        'verify_url' => 'https://www.google.com/recaptcha/api/siteverify',
    ],

    'contact' => [
        'recipient' => env('CONTACT_RECIPIENT'),
    ],

    // Google Agenda : synchronisation des disponibilités (panel → Rendez-vous → Disponibilités).
    // Identifiants OAuth « Application Web » ; URI de redirection : {APP_URL}/<admin>/rendez-vous/google-agenda/retour
    'google_calendar' => [
        'client_id'     => env('GOOGLE_CALENDAR_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
    ],

    // Dépôts GitHub des projets (facultatif : relève la limite de 60 à 5 000 requêtes/heure)
    'github' => [
        'token' => env('GITHUB_TOKEN'),
    ],

    // Machine SSH bac à sable (voir sandbox/README.md) : API de l'agent sur le réseau interne
    'sandbox' => [
        'url'      => env('SANDBOX_API_URL'),
        'token'    => env('SANDBOX_API_TOKEN'),
        'ssh_host' => env('SANDBOX_SSH_HOST'),
        'ssh_port' => (int) env('SANDBOX_SSH_PORT', 2222),
        // Terminal web : par défaut /ssh-ws sur le domaine du portfolio (routeur Traefik de l'agent)
        'ws_url'   => env('SANDBOX_WS_URL'),
    ],

];
