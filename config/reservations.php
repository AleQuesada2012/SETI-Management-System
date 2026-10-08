<?php

// Reservation rules kept in Laravel (Agent-DB.md §9, Q29). Owner: reservations (@JoseHerrera21).
return [
    /*
     * Allowed hours (CONTEXT.md "Allowed hours"): the days and the time window in which a reservation may take place,
     * America/Costa_Rica time. A placeholder until SETI confirms it: Monday to Saturday, 07:00 to 22:00.
     */
    'allowed_hours' => [
        'days' => [1, 2, 3, 4, 5, 6], // ISO-8601 weekdays: 1 = Monday … 7 = Sunday
        'opens' => '07:00',
        'closes' => '22:00',
    ],

    /*
     * Reservations start and end on this grid of minutes from the hour (:00, :15, :30, :45), so the forms offer only
     * these times and the Actions refuse any other.
     */
    'slot_minutes' => 15,
];
