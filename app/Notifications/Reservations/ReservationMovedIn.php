<?php

namespace App\Notifications\Reservations;

/**
 * RF_49 · A reservation was moved here from another space: the Superusuarios and the managers of the new space hear
 * when it takes place, where it came from, by whom and why. Those of the space it left get ReservationMovedOut.
 */
final class ReservationMovedIn extends ReservationModification
{
    public static function type(): string
    {
        return 'reservation.moved_in';
    }

    public static function inboxMessage(array $data): array
    {
        $parts = self::modificationParts($data);

        return [
            'title' => __('reservations.notifications.moved_in.title', ['space' => $parts['space']]),
            'body' => __('reservations.notifications.moved_in.body', $parts),
            'url' => self::reservationUrl($data),
        ];
    }
}
