<?php

return [
    // Unimplemented report types appear as upcoming only when planned after this sprint.
    'current_sprint' => (int) env('REPORTS_CURRENT_SPRINT', 1),

    // RF_61: the Power BI secure-embed URL. Without it, the dashboards page says they are not configured.
    'powerbi_embed_url' => env('POWERBI_EMBED_URL'),

    /*
     * RF_73: the CSV feed Power BI Service refreshes from (routes/powerbi.php). Without a password the feed is off.
     * `connection` is the read-only `powerbi` connection (config/database.php) once its login is configured,
     * otherwise the application's own. `stale_after_hours`: past this without a refresh, the dashboards page warns.
     */
    'powerbi_feed' => [
        'username' => env('POWERBI_FEED_USERNAME', 'powerbi'),
        'password' => env('POWERBI_FEED_PASSWORD'),
        'connection' => env('POWERBI_DB_USERNAME') ? 'powerbi' : null,
        'stale_after_hours' => (int) env('POWERBI_FEED_STALE_AFTER_HOURS', 13),
    ],
];
