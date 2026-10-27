<?php

return [
    'timezone' => env('SIVERA_TIMEZONE', 'Asia/Jakarta'),
    'digest_time' => env('SIVERA_DIGEST_TIME', '07:00'),
    'auto_reopen' => (bool) env('SIVERA_AUTO_REOPEN', true),
    'poll_seconds' => min(10, max(5, (int) env('SIVERA_POLL_SECONDS', 5))),
    'rotation_grace_hours' => 24,
    'retention_years' => max(3, (int) env('SIVERA_RETENTION_YEARS', 3)),
];
