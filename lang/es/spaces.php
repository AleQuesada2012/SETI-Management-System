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
        'filters' => [
            'type' => 'Tipo de espacio',
            'status' => 'Estado',
            'building' => 'Edificio',
            'min_capacity' => 'Capacidad mínima',
            'all' => 'Todos',
            'any' => 'Cualquiera',
            'active' => '{1} 1 filtro activo|[2,*] :count filtros activos',
            'clear' => 'Limpiar filtros',
        ],
    ],
    'show' => [
        'title' => 'Detalle del espacio',
    ],
];
