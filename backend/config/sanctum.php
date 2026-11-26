<?php

return [
    'stateful' => ['admin.taleemfinder.pk', ...explode(',', env(
        'SANCTUM_STATEFUL_DOMAINS',
        'localhost:5173,127.0.0.1:5173,localhost,localhost:8000,127.0.0.1,127.0.0.1:8000,::1',
    ))],
];
