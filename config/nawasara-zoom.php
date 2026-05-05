<?php

return [
    // Sync interval untuk user list (minutes)
    'user_sync_interval' => 60,

    // Sync interval untuk meeting list (minutes)
    'meeting_sync_interval' => 5,

    // Sync interval untuk recording list (minutes)
    'recording_sync_interval' => 30,

    // Records per page
    'per_page' => 25,

    // Cache TTL dalam seconds
    'cache_ttl' => 300,

    // Webhook secret (set via Vault)
    'webhook_secret' => env('ZOOM_WEBHOOK_SECRET', null),
];
