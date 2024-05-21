<?php

return [

    'deposit' => [
        'percent' => env('PERCENT_DEPOSIT_FEE', 2),
        'min' => [
            'NGN' => env('NGN_MIN_DEPOSIT_FEE', 50)
        ],
        'max' => [
            'NGN' => env('NGN_MAX_DEPOSIT_FEE', 500)
        ]
    ],

    'withdraw' => [
        'percent' => env('PERCENT_WITHDRAW_FEE', 5),
        'min' => [
            'NGN' => env('NGN_MIN_WITHDRAW_FEE', 100)
        ],
        'max' => [
            'NGN' => env('NGN_MAX_WITHDRAW_FEE', 1000)
        ]
    ],
];
