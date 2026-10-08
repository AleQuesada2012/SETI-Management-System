<?php

namespace App\Notifications\Reservations;

/**
 * RF_49 · A reservation was moved to another space: the Superusuarios and the managers of the space it left hear
 * which block was freed and where the reservation went. Those of the new space get ReservationMovedIn.
 */
final class ReservationMovedOut extends ReservationModification
{
    public static function type(): string
    {
        return 'reservation.moved_out';
    }

    public static function inboxMessage(array $data): array
    {
        $parts = self::modificationParts($data);

        return [
            'title' => __('reservations.notifications.moved_out.title', ['space' => $parts['previous_space']]),
            'body' => __('reservations.notifications.moved_out.body', $parts),
            'url' => self::reservationUrl($data),
        ];
    }
}
