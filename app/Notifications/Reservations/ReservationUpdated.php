<?php

namespace App\Notifications\Reservations;

/**
 * RF_49 · A reservation was modified without leaving its space: the Superusuarios and the space's managers hear what
 * it is now, what it was, by whom and why.
 */
final class ReservationUpdated extends ReservationModification
{
    public static function type(): string
    {
        return 'reservation.updated';
    }

    public static function inboxMessage(array $data): array
    {
        $parts = self::modificationParts($data);

        return [
            'title' => __('reservations.notifications.updated.title', ['space' => $parts['space']]),
            'body' => __('reservations.notifications.updated.body', $parts),
            'url' => self::reservationUrl($data),
        ];
    }
}
