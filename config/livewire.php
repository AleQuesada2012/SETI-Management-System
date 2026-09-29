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
];
