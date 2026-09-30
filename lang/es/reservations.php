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
    ],
];
