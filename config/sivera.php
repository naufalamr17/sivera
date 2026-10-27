<?php

return [
    'timezone' => env('SIVERA_TIMEZONE', 'Asia/Jakarta'),
    'digest_time' => env('SIVERA_DIGEST_TIME', '07:00'),
    'auto_reopen' => (bool) env('SIVERA_AUTO_REOPEN', true),
    'poll_seconds' => min(10, max(5, (int) env('SIVERA_POLL_SECONDS', 5))),
    'rotation_grace_hours' => 24,
    'retention' => [
        'occurrences_days' => (int) env('SIVERA_OCCURRENCE_RETENTION_DAYS', 30),
        'api_logs_days' => (int) env('SIVERA_API_LOG_RETENTION_DAYS', 30),
        'notification_logs_days' => (int) env('SIVERA_NOTIFICATION_LOG_RETENTION_DAYS', 90),
        'event_receipts_days' => (int) env('SIVERA_EVENT_RECEIPT_RETENTION_DAYS', 365),
        'issues_days' => (int) env('SIVERA_ISSUE_RETENTION_DAYS', 365),
        'audit_logs_days' => (int) env('SIVERA_AUDIT_RETENTION_DAYS', 365),
    ],
];
