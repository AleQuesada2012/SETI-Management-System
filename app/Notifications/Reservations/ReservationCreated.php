<?php

namespace App\Notifications\Reservations;

/**
 * RF_47, RF_48 · A space was reserved (RF_46): the Superusuarios and the space's managers hear which space, when and
 * by whom.
 */
final class ReservationCreated extends ReservationNotification
{
    public static function type(): string
    {
        return 'reservation.created';
    }

    public static function inboxMessage(array $data): array
    {
        $parts = self::messageParts($data);

        return [
            'title' => __('reservations.notifications.created.title', ['space' => $parts['space']]),
            'body' => __('reservations.notifications.created.body', $parts),
            'url' => self::reservationUrl($data),
        ];
    }
}
