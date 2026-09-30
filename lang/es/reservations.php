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
    ],
    'space_reservations' => [
        'heading' => 'Reservas del espacio',
    ],
    'availability_check' => [
        'heading' => 'Disponibilidad',
        'date' => 'Fecha',
        'start_time' => 'Hora de inicio',
        'end_time' => 'Hora de fin',
        'submit' => 'Consultar disponibilidad',
        'range' => ':date, de :start a :end',
        'available' => 'Disponible',
        'unavailable' => 'No disponible',
        'taken_by' => 'Estas reservas ocupan el espacio en ese horario:',
        'reserve' => 'Reservar este horario',
        'not_reservable' => 'Este espacio no se puede reservar por su tipo o su estado actual.',
    ],
];
