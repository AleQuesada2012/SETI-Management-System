<?php

// Strings of the application shell: navigation, error pages and shared UI words.
return [
    'nav' => [
        'dashboard' => 'Tablero',
        'assets' => 'Activos',
        'spaces' => 'Espacios',
        'reservations' => 'Reservas',
        'reports' => 'Reportes',
        'users' => 'Usuarios y roles',
        'settings' => 'Configuración',
        'open_menu' => 'Abrir menú',
    ],

    'errors' => [
        'code' => 'Error :code',
        'home' => 'Volver al inicio',
        'request_id' => 'Código de solicitud: :id',
        403 => [
            'title' => 'Acceso denegado',
            'message' => 'Tu rol no tiene permiso para ver esta página ni para realizar esta acción. Si crees que es un error, comunícate con el Superusuario.',
        ],
        404 => [
            'title' => 'Página no encontrada',
            'message' => 'La página o el registro que buscas no existe o fue eliminado.',
        ],
        419 => [
            'title' => 'La sesión expiró',
            'message' => 'Tu sesión expiró por inactividad. Vuelve a iniciar sesión e intenta de nuevo.',
        ],
        429 => [
            'title' => 'Demasiadas solicitudes',
            'message' => 'Hiciste demasiadas solicitudes seguidas. Espera un momento e intenta de nuevo.',
        ],
        500 => [
            'title' => 'Error del sistema',
            'message' => 'Ocurrió un error inesperado. Intenta de nuevo; si el problema continúa, avisa al equipo con el código de solicitud.',
        ],
        503 => [
            'title' => 'Sistema en mantenimiento',
            'message' => 'El sistema está en mantenimiento. Intenta de nuevo en unos minutos.',
        ],
    ],

    'placeholder' => [
        'heading' => 'En construcción',
        'owner' => 'Responsable: :owner',
        'requirements' => 'Requerimientos',
    ],

    'empty' => 'No hay registros para mostrar.',

    // Morph aliases (audit_logs.entity_type) as a noun, for activity lists.
    'entities' => [
        'user' => 'usuario',
        'role' => 'rol',
        'permission' => 'permiso',
        'space' => 'espacio',
        'asset' => 'activo',
        'asset_component' => 'componente',
        'asset_movement' => 'movimiento',
        'asset_event' => 'evento de activo',
        'maintenance_record' => 'mantenimiento',
        'reservation' => 'reserva',
        'report_generation' => 'reporte',
        'file' => 'archivo',
        'building' => 'edificio',
        'space_type' => 'tipo de espacio',
        'space_status' => 'estado de espacio',
        'asset_category' => 'categoría',
        'asset_type' => 'tipo de activo',
        'asset_condition' => 'condición',
        'brand' => 'marca',
        'supplier' => 'proveedor',
    ],
];
