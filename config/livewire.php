<?php

// Only the settings SETI changes; every other key keeps Livewire's default (vendor/livewire/livewire/config/livewire.php).
return [
    // Full-page components live in resources/views/pages/<module> (`pages::assets.index`); reusable components in
    // resources/views/components/<module> (`<livewire:reservations.space-reservations />`).
    'component_locations' => [
        resource_path('views/components'),
    ],

    'component_namespaces' => [
        'layouts' => resource_path('views/layouts'),
        'pages' => resource_path('views/pages'),
    ],

    'component_layout' => 'layouts::app',

    // Alpine and wire: expressions are evaluated by a parser instead of `new Function()`, so the Content-Security-Policy
    // needs no 'unsafe-eval' (config/csp.php). The parser takes one plain expression: anything with arrow functions,
    // `?.`, `??`, template literals, several statements or globals such as JSON goes in an Alpine.data component in
    // resources/js (two-factor.js, charts.js).
    'csp_safe' => true,

    // `php artisan make:livewire` writes single-file components without the ⚡ file-name prefix (it breaks some
    // Windows tools and shells).
    'make_command' => [
        'type' => 'sfc',
        'emoji' => false,
        'with' => [
            'js' => false,
            'css' => false,
            'test' => false,
        ],
    ],

    // Temporary uploads (RF_20, RF_21, RF_41). Livewire's default stops at 12 MB; documents go up to 20 MB
    // (App\Support\Files\FileRules, Agent-DB.md §11). Each Action validates the real per-kind limits.
    'temporary_file_upload' => [
        'rules' => ['required', 'file', 'max:20480'],
    ],
];
