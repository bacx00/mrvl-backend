<?php

return [
    // Test email configurations for different providers
    'providers' => [
        'gmail' => [
            'transport' => 'smtp',
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
        ],
        'outlook' => [
            'transport' => 'smtp', 
            'host' => 'smtp-mail.outlook.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
        ],
        'yahoo' => [
            'transport' => 'smtp',
            'host' => 'smtp.mail.yahoo.com', 
            'port' => 587,
            'encryption' => 'tls',
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
        ],
        'mailgun' => [
            'transport' => 'mailgun',
            'domain' => env('MAILGUN_DOMAIN'),
            'secret' => env('MAILGUN_SECRET'),
            'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        ],
        'sendgrid' => [
            'transport' => 'smtp',
            'host' => 'smtp.sendgrid.net',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'apikey',
            'password' => env('SENDGRID_API_KEY'),
        ],
        'log' => [
            'transport' => 'log',
            'channel' => 'mail',
        ],
    ],
    
    // Test email addresses for different providers
    'test_recipients' => [
        'gmail' => 'test@gmail.com',
        'outlook' => 'test@outlook.com',
        'yahoo' => 'test@yahoo.com',
        'protonmail' => 'test@protonmail.com',
        'icloud' => 'test@icloud.com',
    ],
];