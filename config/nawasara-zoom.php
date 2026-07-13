<?php

return [
    // Timezone all meetings are scheduled/displayed in. The date picker gives a
    // wall-clock time with no offset; this is the zone Zoom is told to interpret
    // it in, and the zone the UI renders start_time back in.
    'timezone' => env('ZOOM_TIMEZONE', 'Asia/Jakarta'),

    // Scheduler — set false to disable all automatic Zoom syncing.
    'scheduler' => [
        'enabled' => env('ZOOM_SCHEDULER_ENABLED', true),
    ],

    // Sync cadence (minutes). cron is */{interval}, so keep ≤ 60.
    'user_sync_interval' => env('ZOOM_USER_SYNC_INTERVAL', 60),
    'meeting_sync_interval' => env('ZOOM_MEETING_SYNC_INTERVAL', 5),
    'recording_sync_interval' => env('ZOOM_RECORDING_SYNC_INTERVAL', 30),

    // How far back the daily history backfill reaches (months). Uses the Zoom
    // Reports API (needs report:read:admin scope on the Zoom app).
    'history_months' => env('ZOOM_HISTORY_MONTHS', 6),

    // Records per page (UI pagination).
    'per_page' => 25,

    // Cache TTL (seconds).
    'cache_ttl' => 300,

    // Webhook secret.
    'webhook_secret' => env('ZOOM_WEBHOOK_SECRET', null),
];
