<?php

return [
    'queues' => [
        'high' => env('PAYMENTS_QUEUE_HIGH', 'payments-high'),
        'polling' => env('PAYMENTS_QUEUE', 'payments'),
    ],
];
