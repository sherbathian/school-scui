<?php

return [
    'pilot_reader' => env('FINANCE_PILOT_READER', 'legacy'),
    'operational' => [
        'enabled' => env('FINANCE_OPERATIONAL_ENABLED', false),
        'branch_id' => env('FINANCE_OPERATIONAL_BRANCH_ID'),
        'currency' => env('FINANCE_OPERATIONAL_CURRENCY'),
        'writes_enabled' => env('FINANCE_OPERATIONAL_WRITES_ENABLED', false),
    ],
];
