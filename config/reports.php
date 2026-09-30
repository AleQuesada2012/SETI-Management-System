<?php

return [
    // Unimplemented report types appear as upcoming only when planned after this sprint.
    'current_sprint' => (int) env('REPORTS_CURRENT_SPRINT', 1),
];
