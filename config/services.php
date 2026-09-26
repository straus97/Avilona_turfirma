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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'sletat' => [
        'login' => env('SLETAT_LOGIN'),
        'password' => env('SLETAT_PASSWORD'),
        'timeout' => env('SLETAT_TIMEOUT', 30),
    ],

    // Tourvisor: экспорт заявок (входящие обращения). Публичный ID модуля
    // (9981450) не секрет; ключ экспорта выдаётся поддержкой Tourvisor и
    // задаётся ТОЛЬКО в серверном окружении. Значения по умолчанию нет.
    'tourvisor' => [
        'export_api_key' => env('TOURVISOR_EXPORT_API_KEY'),
        // Секрет пути callback-URL webhook. НЕ ключ Export API: задаётся нами, меняется
        // независимо. 32–128 символов [A-Za-z0-9_-], значения по умолчанию нет
        // (пусто/слабо → webhook закрыт, fail closed).
        'webhook_token' => env('TOURVISOR_WEBHOOK_TOKEN'),
        'timeout' => (int) env('TOURVISOR_EXPORT_TIMEOUT', 10),
        'connect_timeout' => (int) env('TOURVISOR_EXPORT_CONNECT_TIMEOUT', 5),
    ],

];
