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

    // Se usa el mailer "resend" (API HTTPS, puerto 443) y no SMTP porque
    // Railway deshabilita el SMTP saliente en los planes Free/Trial/Hobby: la
    // conexión a smtp.resend.com:587 hacía timeout en producción. En Resend la
    // contraseña SMTP y la API key son el mismo valor "re_...", así que si no
    // se define RESEND_API_KEY se reutiliza MAIL_PASSWORD en vez de duplicar
    // el secreto en otra variable.
    'resend' => [
        'key' => env('RESEND_API_KEY', env('MAIL_PASSWORD')),
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

    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase-service-account.json')),
    ],

];
