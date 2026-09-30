<?php

// Spaces module (RF_32–RF_45, RF_53–RF_59).
return [
    'index' => [
        'title' => 'Espacios',
        'create' => 'Añadir espacio',
    ],
    'show' => [
        'title' => 'Detalle del espacio',
        'occupied' => 'Ocupado ahora',
        'details' => 'Información del espacio',
        'type' => 'Tipo de espacio',
        'capacity' => 'Capacidad',
        'people' => '{1} :count persona|[2,*] :count personas',
        'location' => 'Ubicación',
        'managers' => 'Encargados del espacio',
        'no_managers' => 'Sin encargados asignados',
        'notes' => 'Notas',
        'assets' => 'Activos en el espacio (:count)',
        'no_assets' => 'Este espacio no tiene activos registrados.',
        'asset_code' => 'Código',
        'asset_name' => 'Activo',
        'asset_type' => 'Tipo',
        'asset_condition' => 'Condición',
        'asset_status' => 'Estado',
    ],
    'create' => [
        'title' => 'Añadir espacio',
        'description' => 'Registra un laboratorio, oficina o sala. Empieza con el estado Disponible.',
        'code_placeholder' => 'LAB-110',
        'location_placeholder' => 'Ala norte, al lado de la biblioteca',
        'choose' => 'Selecciona una opción',
        'cancel' => 'Cancelar',
        'submit' => 'Guardar espacio',
        'done' => 'El espacio :space se registró correctamente.',
    ],
    // Field names used in forms and in validation messages.
    'fields' => [
        'code' => 'código',
        'name' => 'nombre',
        'space_type_id' => 'tipo de espacio',
        'building_id' => 'edificio',
        'floor' => 'piso',
        'location_detail' => 'detalle de la ubicación',
        'capacity' => 'capacidad',
        'notes' => 'notas',
    ],
];
