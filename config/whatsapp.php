<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp ticket delivery packages
    |--------------------------------------------------------------------------
    |
    | Each package maps to a top-up the organizer can buy from the dashboard.
    | `quota` is the number of ticket messages credited to the organizer.
    |
    */
    'packages' => [
        'starter' => [
            'label' => 'Starter',
            'quota' => 100,
            'amount' => 50000,
        ],
        'growth' => [
            'label' => 'Growth',
            'quota' => 500,
            'amount' => 200000,
        ],
        'scale' => [
            'label' => 'Scale',
            'quota' => 2000,
            'amount' => 700000,
        ],
    ],
];
