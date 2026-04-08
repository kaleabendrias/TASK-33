<?php

return [
    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Channels
    |--------------------------------------------------------------------------
    | Logs are segmented into dedicated streams so operators can route
    | each category independently rather than tailing a single firehose:
    |
    |   - security : auth, JWT, permission, and audit-trail events
    |   - business : domain events (orders, bookings, settlements)
    |   - errors   : uncaught exceptions and application errors
    |   - stderr   : raw fallback used by the legacy stack
    |
    | The default 'stack' channel fans out to all of them so existing
    | call sites that use Log::info() / Log::error() continue to work.
    */
    'channels' => [
        'stack' => [
            'driver'   => 'stack',
            'channels' => ['stderr', 'errors'],
        ],

        'stderr' => [
            'driver'  => 'monolog',
            'level'   => env('LOG_LEVEL', 'debug'),
            'handler' => Monolog\Handler\StreamHandler::class,
            'with'    => [
                'stream' => 'php://stderr',
            ],
        ],

        // Security-relevant events: auth attempts, token issuance and
        // revocation, permission denials, audit-log writes.
        'security' => [
            'driver'  => 'monolog',
            'level'   => env('LOG_LEVEL_SECURITY', 'info'),
            'handler' => Monolog\Handler\StreamHandler::class,
            'with'    => [
                'stream' => 'php://stderr',
            ],
            'formatter' => Monolog\Formatter\JsonFormatter::class,
            'tap'       => [],
        ],

        // Business / domain events: orders created, bookings confirmed,
        // settlements generated, refunds processed, etc.
        'business' => [
            'driver'  => 'monolog',
            'level'   => env('LOG_LEVEL_BUSINESS', 'info'),
            'handler' => Monolog\Handler\StreamHandler::class,
            'with'    => [
                'stream' => 'php://stderr',
            ],
            'formatter' => Monolog\Formatter\JsonFormatter::class,
        ],

        // Application errors: uncaught exceptions, validation faults
        // bubbling out of controllers, infrastructure failures.
        'errors' => [
            'driver'  => 'monolog',
            'level'   => env('LOG_LEVEL_ERRORS', 'warning'),
            'handler' => Monolog\Handler\StreamHandler::class,
            'with'    => [
                'stream' => 'php://stderr',
            ],
            'formatter' => Monolog\Formatter\JsonFormatter::class,
        ],
    ],
];
