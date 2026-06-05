<?php

$useRealInbox = filter_var(env('MAIL_USE_REAL_INBOX', false), FILTER_VALIDATE_BOOL);

$smtpMailer = $useRealInbox
    ? [
        'transport' => 'smtp',
        'host' => env('MAIL_REAL_HOST', 'smtp.gmail.com'),
        'port' => (int) env('MAIL_REAL_PORT', 587),
        'encryption' => env('MAIL_REAL_ENCRYPTION', 'tls') ?: null,
        'username' => env('MAIL_REAL_USERNAME'),
        'password' => env('MAIL_REAL_PASSWORD'),
        'timeout' => null,
    ]
    : [
        'transport' => 'smtp',
        'host' => env('MAIL_HOST', 'localhost'),
        'port' => (int) env('MAIL_PORT', 1025),
        'encryption' => env('MAIL_ENCRYPTION') ?: null,
        'username' => env('MAIL_USERNAME'),
        'password' => env('MAIL_PASSWORD'),
        'timeout' => null,
    ];

$fromAddress = $useRealInbox
    ? (env('MAIL_REAL_FROM_ADDRESS') ?: env('MAIL_REAL_USERNAME') ?: env('MAIL_FROM_ADDRESS', 'no-reply@smartfleet.local'))
    : env('MAIL_FROM_ADDRESS', 'no-reply@smartfleet.local');

return [
    'default' => env('MAIL_MAILER', 'smtp'),

    'real_inbox' => $useRealInbox,

    'mailers' => [
        'smtp' => $smtpMailer,
        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],
        'array' => [
            'transport' => 'array',
        ],
    ],

    'from' => [
        'address' => $fromAddress,
        'name' => env('MAIL_FROM_NAME', 'Smart Fleet'),
    ],
];
