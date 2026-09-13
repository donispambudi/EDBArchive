<?php

return [
    'worker' => [
        'host' => env('EDB_WORKER_HOST', '127.0.0.1'),
        'port' => (int) env('EDB_WORKER_PORT', 9100),
        'timeout' => (float) env('EDB_WORKER_TIMEOUT', 1.0),
    ],
];
