<?php

// Spaces module (RF_32–RF_45, RF_53–RF_59).
return [
    'index' => [
        'title' => 'Espacios',
        'create' => 'Añadir espacio',
        // RF_53 list and RF_54 filters (owner: reservations, @JoseHerrera21).
        'total' => '{0} Sin espacios|{1} 1 espacio|[2,*] :count espacios',
        'empty' => 'No hay espacios registrados.',
        'no_matches' => 'No se encontraron espacios con los criterios aplicados.',
        'occupied' => 'Ocupado ahora',
        'columns' => [
            'name' => 'Espacio',
            'location' => 'Ubicación',
            'capacity' => 'Capacidad',
            'type' => 'Tipo',
            'status' => 'Estado',
        ],
        'active_filters' => 'Filtros activos',
        'clear_filters' => 'Limpiar filtros',
        'invalid_filters' => 'Los filtros no son válidos',
    ],
    'show' => [
        'title' => 'Detalle del espacio',
    ],
    // The spaces filter contract (.ai/rules/reports.md § Spaces schema), shared by the list and the spaces report.
    'filters' => [
        'criteria' => [
            'space_type' => 'Tipo de espacio',
            'space_status' => 'Estado',
            'building' => 'Edificio',
        ],
        'removed' => ':name (eliminado)',
        'errors' => [
            'unknown_key' => 'El filtro «:key» no existe.',
            'shape' => 'El filtro «:filter» no tiene un formato válido.',
            'unknown_value' => 'El filtro «:filter» tiene una opción que no existe.',
            'search' => 'El texto de búsqueda no es válido (máximo 200 caracteres).',
        ],
    ],
];
