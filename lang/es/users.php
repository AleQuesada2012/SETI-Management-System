<?php

// Users and roles module (RF_85–RF_101).
return [
    'index' => [
        'title' => 'Usuarios y roles',
        'create' => 'Añadir usuario',
        'roles' => 'Roles',
        'search' => 'Buscar',
        'search_placeholder' => 'Nombre, correo o ID',
        'role' => 'Rol',
        'all_roles' => 'Todos los roles',
        'account_status' => 'Estado de cuenta',
        'all_statuses' => 'Todos los estados',
        'clear' => 'Limpiar filtros',
        'empty' => 'No hay usuarios registrados.',
        'no_results' => 'No se encontraron usuarios con los criterios indicados.',
    ],
    'fields' => [
        'name' => 'Nombre',
        'email' => 'Correo',
        'roles' => 'Roles',
        'status' => 'Estado',
        'no_roles' => 'Sin rol',
        'last_login_at' => 'Último inicio de sesión',
        'created_at' => 'Registrado',
        'deactivated_at' => 'Desactivada el',
    ],
    'status' => [
        'active' => 'Activa',
        'deactivated' => 'Desactivada',
        'invitation_pending' => 'Invitación pendiente',
    ],
    'show' => [
        'title' => 'Detalle del usuario',
        'back' => 'Volver a usuarios',
        'never' => 'Nunca',
    ],
    'roles' => [
        'title' => 'Roles',
    ],
];
