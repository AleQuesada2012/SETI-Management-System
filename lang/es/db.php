<?php

/*
 * Spanish messages for database rules (Agent-DB.md §14.11). The key is the constraint or trigger
 * rule name that PostgreSQL puts in the error message; App\Support\Database\ConstraintViolation
 * extracts it. Add a line here whenever a migration adds a new named rule users can hit.
 */

return [
    'unknown' => 'No se pudo guardar porque los datos no cumplen una regla del sistema.',
    'user_invitation_tokens_pkey' => 'Ya existe una invitación para ese correo.',

    // Assets and their history
    'assets_asset_tag_unique' => 'Ya existe un activo con esa placa.',
    'assets_location_via_movements' => 'La ubicación de un activo solo cambia registrando un movimiento.',
    'assets_warranty_pair_check' => 'La garantía necesita fecha de inicio y fecha de fin.',
    'assets_warranty_range_check' => 'La garantía no puede terminar antes de comenzar.',
    'assets_status_check' => 'El estado del ciclo de vida no es válido.',
    'asset_movements_origin_matches' => 'El activo no está en el espacio de origen indicado.',
    'asset_movements_asset_rules' => 'Este activo no se puede mover: está eliminado, perdido o dado de baja.',
    'asset_movements_destination_active' => 'No se puede mover un activo a un espacio eliminado.',
    'asset_movements_chronological' => 'El activo ya tiene un movimiento posterior a esa fecha.',
    'asset_movements_endpoints_check' => 'El origen y el destino del movimiento no son válidos.',
    'asset_events_one_decommission' => 'Este activo ya fue dado de baja.',
    'asset_events_description_check' => 'Escribe una descripción del evento.',
    'asset_components_name_check' => 'El componente necesita un nombre.',
    'asset_components_quantity_check' => 'La cantidad debe ser mayor que cero.',
    'asset_files_file_id_unique' => 'Ese archivo ya pertenece a otro registro.',
    'asset_event_evidence_file_id_unique' => 'Ese archivo ya pertenece a otro registro.',
    'asset_files_kind_check' => 'El archivo debe ser una fotografía o un documento.',
    'maintenance_records_scheduled_check' => 'Un mantenimiento programado necesita la fecha programada.',
    'maintenance_records_started_check' => 'Un mantenimiento en curso necesita la fecha de inicio.',
    'maintenance_records_completed_check' => 'Un mantenimiento completado necesita la fecha de finalización.',
    'maintenance_records_dates_check' => 'La fecha de finalización no puede ser anterior a la de inicio.',
    'maintenance_records_description_check' => 'Escribe una descripción del mantenimiento.',
    'maintenance_records_event_kind_check' => 'Solo un mantenimiento correctivo puede atender un daño.',
    'maintenance_records_asset_event_foreign' => 'El daño indicado no pertenece a este activo.',
    'maintenance_records_cancellation_check' => 'Solo un mantenimiento cancelado lleva motivo de cancelación.',

    // Spaces
    'spaces_code_unique' => 'Ya existe un espacio con ese código.',
    'spaces_name_unique' => 'Ya existe un espacio con ese nombre.',
    'spaces_capacity_check' => 'La capacidad debe ser un número entero mayor que cero.',
    'spaces_code_check' => 'El espacio necesita un código.',
    'spaces_name_check' => 'El espacio necesita un nombre.',
    'space_photos_file_id_unique' => 'Esa fotografía ya pertenece a otro registro.',

    // Reservations
    'reservations_no_overlap' => 'El espacio ya está reservado en ese horario.',
    'reservations_period_check' => 'La reserva debe terminar después de comenzar.',
    'reservations_attendees_check' => 'La cantidad de personas debe ser mayor que cero.',
    'reservations_title_check' => 'Escribe el motivo de la reserva.',
    'reservations_cancellation_check' => 'Los datos de la cancelación no son coherentes.',

    // Catalogs
    'buildings_name_unique' => 'Ya existe un edificio con ese nombre.',
    'space_types_name_unique' => 'Ya existe un tipo de espacio con ese nombre.',
    'space_statuses_name_unique' => 'Ya existe un estado con ese nombre.',
    'asset_categories_name_unique' => 'Ya existe una categoría con ese nombre.',
    'asset_types_name_unique' => 'Ya existe un tipo de activo con ese nombre.',
    'asset_conditions_name_unique' => 'Ya existe una condición con ese nombre.',
    'brands_name_unique' => 'Ya existe una marca con ese nombre.',
    'suppliers_name_unique' => 'Ya existe un proveedor con ese nombre.',
    'suppliers_tax_id_unique' => 'Ya existe un proveedor con esa cédula jurídica.',
    'buildings_name_check' => 'El nombre no puede quedar vacío.',
    'space_types_name_check' => 'El nombre no puede quedar vacío.',
    'space_statuses_name_check' => 'El nombre no puede quedar vacío.',
    'asset_categories_name_check' => 'El nombre no puede quedar vacío.',
    'asset_types_name_check' => 'El nombre no puede quedar vacío.',
    'asset_conditions_name_check' => 'El nombre no puede quedar vacío.',
    'brands_name_check' => 'El nombre no puede quedar vacío.',
    'suppliers_name_check' => 'El nombre no puede quedar vacío.',

    // Users and roles
    'users_email_unique' => 'Ya existe un usuario con ese correo electrónico.',
    'users_email_format_check' => 'El correo electrónico no es válido.',
    'users_email_lowercase_check' => 'El correo electrónico debe guardarse en minúsculas.',
    'users_name_check' => 'El usuario necesita un nombre.',
    'roles_name_guard_name_unique' => 'Ya existe un rol con ese nombre.',

    // Files and reports
    'files_mime_type_check' => 'Ese tipo de archivo no está permitido.',
    'files_size_check' => 'El archivo es demasiado grande (imágenes: 10 MB; otros: 20 MB).',
    'report_generations_format_check' => 'Los reportes se exportan en PDF o XLSX.',
];
