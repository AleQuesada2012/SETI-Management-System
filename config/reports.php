<?php

return [
    // Unimplemented report types appear as upcoming only when planned after this sprint.
    'current_sprint' => (int) env('REPORTS_CURRENT_SPRINT', 1),

    // RF_61: the Power BI secure-embed URL. Without it, the dashboards page says they are not configured.
    'powerbi_embed_url' => env('POWERBI_EMBED_URL'),
];
