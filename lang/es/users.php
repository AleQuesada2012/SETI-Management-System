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
    'register' => [
        'heading' => 'Añadir usuario',
        'help' => 'La persona recibirá un correo con un enlace para definir su contraseña.',
        'cancel' => 'Cancelar',
        'submit' => 'Registrar',
        'done' => 'Se registró a :user y se envió la invitación a :email.',
    ],
    'show' => [
        'title' => 'Detalle del usuario',
    ],
    'roles' => [
        'title' => 'Roles',
    ],
];
