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
        'heading' => 'Nueva reserva',
        'description' => 'Elige un espacio, la fecha y el horario. :hours',
        'choose_space' => 'Selecciona un espacio',
        'space_hint' => ':location · Capacidad: :capacity personas',
        'title_placeholder' => 'Tutoría de cálculo, reunión de proyecto…',
        'cancel' => 'Cancelar',
        'submit' => 'Reservar',
        'done' => 'Se reservó :space.',
        'errors' => [
            'not_reservable' => 'Este espacio no se puede reservar por su tipo o su estado actual.',
            'in_past' => 'La reserva debe comenzar después de este momento.',
            'outside_hours' => 'Solo se puede reservar los días :days, de :opens a :closes.',
            'over_capacity' => 'La cantidad de personas supera la capacidad del espacio (:capacity).',
            'overlap' => 'El espacio ya está reservado en ese horario. Revisa las reservas del espacio o el calendario y elige otro horario.',
        ],
    ],
    // ISO-8601 weekdays of the allowed hours (config/reservations.php).
    'weekdays' => [
        1 => 'lunes',
        2 => 'martes',
        3 => 'miércoles',
        4 => 'jueves',
        5 => 'viernes',
        6 => 'sábado',
        7 => 'domingo',
        'and' => 'y',
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
        'from' => 'Desde',
        'to' => 'Hasta',
        'submit' => 'Consultar',
        'empty' => 'El espacio no tiene reservas en ese período.',
        'total' => '{1} 1 reserva entre el :from y el :to|[2,*] :count reservas entre el :from y el :to',
        'columns' => [
            'date' => 'Fecha',
            'time' => 'Horario',
            'title' => 'Motivo',
            'responsible' => 'Responsable',
            'attendees' => 'Personas',
            'status' => 'Estado',
        ],
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
    // Messages of the bell (RF_47, RF_48), built from each notification's snapshot.
    'notifications' => [
        'period' => ':date, de :start a :end',
        'created' => [
            'title' => 'Nueva reserva en :space',
            'body' => ':actor reservó el espacio para «:title»: :periods.',
        ],
    ],
];
