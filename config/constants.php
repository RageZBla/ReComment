<?php
return [
    'sync_minutes' => env('APP_SYNC_MINUTES', 5),
    'purge_minutes' => env('APP_PURGE_MINUTES', 10),
    'seed' => [
        'number_users' => env('APP_SEED_NUMBER_USERS', 10),
        'number_comments' => env('APP_SEED_NUMBER_COMMENTS', 100),
    ]
];
