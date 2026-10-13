<?php

// Spaces module (RF_32–RF_45, RF_53–RF_59).
return [
    // RF_45 · the reservations calendar (pages/reservations/index).
    'calendar' => [
        'previous' => 'Mes anterior',
        'next' => 'Mes siguiente',
        'weekdays' => ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
        'reservations' => '{1} 1 reserva|[2,*] :count reservas',
        'free' => 'libre',
        'legend_reserved' => 'Con reservas',
        'legend_free' => 'Libre',
        'pick_day' => 'Selecciona un día para ver sus reservas.',
        'day_free' => 'No hay reservas este día: todos los espacios están libres.',
        'by' => 'Reservado para :name',
    ],
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
    'edit' => [
        'button' => 'Editar',
        'title' => 'Editar espacio',
        'description' => 'Cambia los datos del espacio. El estado se cambia aparte.',
        'submit' => 'Guardar cambios',
        'done' => 'Los cambios de :space se guardaron correctamente.',
    ],
    'status' => [
        'button' => 'Cambiar estado',
        'confirm_title' => '¿Cambiar el estado a :status?',
        'affected' => '{1} Este espacio tiene 1 reserva confirmada que aún no termina. Con este estado no se puede reservar, pero la reserva seguirá confirmada hasta que alguien la cancele.|[2,*] Este espacio tiene :count reservas confirmadas que aún no terminan. Con este estado no se puede reservar, pero las reservas seguirán confirmadas hasta que alguien las cancele.',
        'confirm' => 'Cambiar estado',
        'done' => 'El estado de :space ahora es :status.',
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

    // Space managers (RF_40, RF_43 · @Rubiocai). The user page shows and edits them; see lang/es/users.php.
    'managers' => [
        'spaces' => 'espacios',
        'not_administrador' => ':user no tiene el rol Administrador; solo los Administradores pueden estar a cargo de espacios.',
    ],
];
