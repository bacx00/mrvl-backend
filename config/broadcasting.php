<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This controls the default broadcaster that will be used by the framework
    | when an event needs to be broadcast. You may set this to any of the
    | connections defined in the "connections" array below.
    |
    */

    'default' => env('BROADCAST_DRIVER', 'pusher'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over websockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    'connections' => [

        'pusher' => [
            'driver'        => 'pusher',
            'key'           => env('PUSHER_APP_KEY'),
            'secret'        => env('PUSHER_APP_SECRET'),
            'app_id'        => env('PUSHER_APP_ID'),
            'options'       => [
                'cluster'   => env('PUSHER_APP_CLUSTER'),
                'useTLS'    => true,
                // If you run your own websocket server rather than Pusher.com:
                'host'      => env('PUSHER_HOST', '127.0.0.1'),
                'port'      => env('PUSHER_PORT', 6001),
                'scheme'    => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
            ],
        ],

        /*
        // Redis broadcasting disabled - using 'log' or 'null' instead
        'redis' => [
            'driver'     => 'redis',
            'connection' => env('BROADCAST_REDIS_CONNECTION', 'default'),
        ],
        */

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
