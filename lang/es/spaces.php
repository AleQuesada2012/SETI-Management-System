<?php

// Spaces module (RF_32–RF_45, RF_53–RF_59).
return [
    'index' => [
        'title' => 'Espacios',
        'create' => 'Añadir espacio',
    ],
    'show' => [
        'title' => 'Detalle del espacio',
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
    'search' => [
        'label' => 'Buscar espacios',
        'placeholder' => 'Buscar por nombre, ubicación o código',
        'found' => '{1} 1 espacio coincide con la búsqueda.|[2,*] :count espacios coinciden con la búsqueda.',
        'none' => 'No existe ningún espacio que coincida con «:term».',
        'code' => 'Código',
        'name' => 'Nombre',
        'location' => 'Ubicación',
        'type' => 'Tipo',
        'status' => 'Estado',
    ],
];
