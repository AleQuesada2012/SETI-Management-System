<?php

// Reservations module (RF_45–RF_52, RF_55, RF_57).
return [
    // Field names used in forms and in validation messages.
    'fields' => [
        'space_id' => 'espacio',
        'date' => 'fecha',
        'start_time' => 'hora de inicio',
        'end_time' => 'hora de fin',
        'title' => 'motivo',
        'attendees' => 'cantidad de personas',
        'responsible_id' => 'responsable',
        'notes' => 'notas',
        'from' => 'fecha inicial',
        'to' => 'fecha final',
    ],
    'validation' => [
        'end_after_start' => 'La hora de fin debe ser posterior a la hora de inicio.',
        'to_after_from' => 'La fecha final no puede ser anterior a la fecha inicial.',
    ],
    // Next to the effective status (ReservationEffectiveStatus::label()).
    'status' => [
        'modified' => 'Modificada',
    ],
    'index' => [
        'title' => 'Reservas',
        'create' => 'Reservar espacio',
    ],
    'create' => [
        'title' => 'Reservar espacio',
    ],
    'show' => [
        'title' => 'Detalle de la reserva',
        'space' => 'Espacio',
        'date' => 'Fecha',
        'time' => 'Horario',
        'reason' => 'Motivo',
        'attendees' => 'Cantidad de personas',
        'responsible' => 'Responsable',
        'created_by' => 'Reservada por',
        'notes' => 'Notas',
        'status' => 'Estado actual',
        'deleted_space' => '(espacio eliminado)',
        'cancelled' => 'Esta reserva fue cancelada el :date por :by.',
        'cancellation_reason' => 'Motivo de la cancelación: :reason',
        'update' => 'Modificar',
        'cancel' => 'Eliminar',
        'coming_soon' => 'Disponible próximamente',
    ],
    'space_reservations' => [
        'heading' => 'Reservas del espacio',
    ],
    'availability_check' => [
        'heading' => 'Disponibilidad',
    ],
];
