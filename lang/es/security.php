<?php

// Security module: sign-in (RF_00_1), the user's own profile and the audit trail (RF_00_2, RF_00_3).
return [
    'login' => [
        'subtitle' => 'Sistema de Gestión de Activos y Espacios',
        'submit' => 'Ingresar al sistema',
    ],

    'two_factor' => [
        'required' => 'Tu rol exige la autenticación en dos pasos. Actívala aquí para continuar usando el sistema.',
    ],

    'profile' => [
        'subheading' => 'Actualiza tu nombre visible',
        'email_readonly' => 'Tu correo es tu usuario de acceso; solo el Superusuario puede cambiarlo.',
        'roles' => 'Tus roles',
    ],
];
