<?php

namespace App\Notifications\Reservations;

/**
 * RF_50 · A reservation was cancelled: the Superusuarios and the space's managers hear
 * which space and block were freed, and by whom (RF_50 diagram).
 */
final class ReservationCancelled extends ReservationNotification
{
    public static function type(): string
    {
        return 'reservation.cancelled';
    }

    public static function inboxMessage(array $data): array
    {
        $parts = self::messageParts($data);

        return [
            'title' => __('reservations.notifications.cancelled.title', ['space' => $parts['space']]),
            'body' => __('reservations.notifications.cancelled.body', $parts),
            'url' => self::reservationUrl($data),
        ];
    }
}
