<?php

// Users and roles module (RF_85–RF_101).
return [
    'index' => [
        'title' => 'Usuarios y roles',
        'create' => 'Añadir usuario',
        'roles' => 'Roles',
        'role' => 'Rol',
        'all_roles' => 'Todos los roles',
        'account_status' => 'Estado de cuenta',
        'all_statuses' => 'Todos los estados',
        'clear' => 'Limpiar filtros',
        'empty' => 'No hay usuarios registrados.',
        'no_results' => 'No hay usuarios con los filtros indicados.',
    ],
    'fields' => [
        'name' => 'Nombre',
        'email' => 'Correo',
        'roles' => 'Roles',
        'status' => 'Estado',
        'no_roles' => 'Sin rol',
    ],
    'status' => [
        'active' => 'Activa',
        'deactivated' => 'Desactivada',
        'invitation_pending' => 'Invitación pendiente',
    ],
    'show' => [
        'title' => 'Detalle del usuario',
    ],
    'roles' => [
        'title' => 'Roles',
        'intro' => 'Cada usuario puede tener varios roles; sus permisos son la suma de los permisos de sus roles.',
        'back' => 'Volver a usuarios',
        'system' => 'Rol del sistema',
        'holders_count' => '{0} Sin usuarios|{1} 1 usuario|[2,*] :count usuarios',
        'permissions_count' => '{0} Sin permisos|{1} Qué permite (1 permiso)|[2,*] Qué permite (:count permisos)',
        'no_permissions' => 'Este rol no tiene permisos.',
        'show_holders' => 'Ver usuarios',
        'hide_holders' => 'Ocultar usuarios',
        'holders_heading' => 'Usuarios con el rol :role',
        'no_holders' => 'Ningún usuario tiene este rol.',
    ],
];
