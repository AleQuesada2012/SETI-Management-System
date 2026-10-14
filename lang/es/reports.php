<?php

// Reports module (RF_00_4, RF_60–RF_84).
return [
    'warranties' => [
        'supplier' => 'Proveedor', 'starts_on' => 'Inicio de la garantía', 'ends_on' => 'Fin de la garantía',
        'state' => 'Vigencia de la garantía', 'states' => ['valid' => 'Vigente', 'expired' => 'Vencida'],
        'no_supplier' => 'Sin proveedor registrado',
    ],
    'users' => [
        'name' => 'Nombre', 'email' => 'Correo', 'roles' => 'Roles',
        'account_status' => 'Estado de cuenta', 'invitation_pending' => 'Invitación pendiente',
    ],
    'audit' => [
        'actor' => 'Usuario', 'action' => 'Tipo de operación', 'occurred_at' => 'Fecha de la operación',
        'columns' => [
            'actor_label' => 'Usuario registrado', 'occurred_at' => 'Fecha y hora', 'action' => 'Operación',
            'entity_label' => 'Elemento registrado', 'old_values' => 'Valores anteriores', 'new_values' => 'Valores nuevos',
        ],
    ],
    'index' => [
        'title' => 'Reportes',
        'generations' => 'Reportes generados',
        'category' => 'Categoría',
        'open' => 'Ver reporte',
        'upcoming' => 'Próximamente',
        'empty' => 'No hay reportes disponibles para esta categoría.',
    ],
    'show' => [
        'unavailable' => 'Este reporte todavía no está disponible.',
        'back' => 'Volver a reportes',
        'preview' => 'Vista previa',
        'preview_limit' => 'Se muestran los primeros :shown de :total registros. La exportación incluye todos.',
        'total' => ':total registros',
    ],
    'columns' => [
        'code' => 'Código de activo', 'asset_tag' => 'Placa', 'display_name' => 'Nombre',
        'brand' => 'Marca', 'model' => 'Modelo', 'condition' => 'Condición física',
        'status' => 'Estado del ciclo de vida', 'space_name' => 'Espacio',
        'responsible_name' => 'Responsable del activo', 'acquired_on' => 'Fecha de adquisición',
        'space_report_name' => 'Nombre', 'space_location' => 'Ubicación', 'space_capacity' => 'Capacidad',
        'space_space_type' => 'Tipo', 'space_status' => 'Estado operativo', 'space_managers' => 'Encargados del espacio',
    ],
    'filters' => [
        'instructions' => 'Selecciona una o varias opciones. Sin selección, no se restringen los registros. Los criterios se combinan entre sí.',
        'clear' => 'Limpiar filtros', 'from' => 'Desde', 'to' => 'Hasta',
        'invalid' => 'Revisa los filtros antes de visualizar o exportar el reporte.',
        'removed' => ':name (eliminado)', 'deactivated' => ':name (cuenta desactivada)',
        'colors' => ['green' => 'Verde', 'amber' => 'Ámbar', 'red' => 'Rojo'],
        'labels' => [
            'search' => 'Buscar', 'brand' => 'Marca', 'model' => 'Modelo contiene', 'category' => 'Categoría',
            'condition' => 'Condición física', 'condition_color' => 'Color de condición',
            'lifecycle_status' => 'Estado del ciclo de vida', 'space' => 'Espacio',
            'responsible_user' => 'Responsable del activo', 'acquired_on' => 'Fecha de adquisición',
            'space_type' => 'Tipo de espacio', 'space_status' => 'Estado operativo', 'building' => 'Edificio',
        ],
        'missing' => [
            'brand' => 'Sin marca', 'model' => 'Sin modelo', 'space' => 'Sin espacio',
            'responsible_user' => 'Sin responsable', 'acquired_on' => 'Sin fecha de adquisición',
        ],
        'errors' => [
            'unknown' => 'El filtro :key no está disponible.',
            'shape' => 'La estructura del filtro no es válida.',
            'text' => 'Introduce un texto de hasta 200 caracteres.',
            'value' => 'Selecciona una opción válida del filtro.',
            'boolean' => 'La selección de valores sin información debe ser verdadera o falsa.',
            'date' => 'Introduce una fecha válida con formato AAAA-MM-DD.',
            'range' => 'La fecha final no puede ser anterior a la inicial.',
        ],
    ],
    'generations' => [
        'title' => 'Reportes generados',
    ],
    'export' => [
        'button' => 'Exportar',
        'pdf' => 'Exportar PDF',
        'xlsx' => 'Exportar Excel (XLSX)',
        'done' => 'El reporte se generó correctamente.',
        'invalid_format' => 'Selecciona PDF o Excel (XLSX) para exportar el reporte.',
        'sheet_data' => 'Datos',
        'sheet_info' => 'Información',
    ],
    'document' => [
        'report' => 'Reporte',
        'generated_at' => 'Generado el',
        'generated_by' => 'Generado por',
        'records' => 'Registros',
        'filters' => 'Filtros aplicados',
        'filter' => 'Filtro: :filter',
        'all_records' => 'ninguno (contiene la totalidad de los registros)',
        'empty' => 'No hay registros que cumplan los filtros.',
        'page' => 'Página',
    ],
];
