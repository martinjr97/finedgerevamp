<?php

return [
    'queues' => [
        'high' => env('PAYMENTS_QUEUE_HIGH', 'payments-high'),
        'polling' => env('PAYMENTS_QUEUE', 'payments'),
        'collections_high' => env('PAYMENTS_QUEUE_HIGH', 'payments-high'),
        'disbursements_high' => env('DISBURSEMENTS_QUEUE_HIGH', 'disbursements-high'),
    ],
];
