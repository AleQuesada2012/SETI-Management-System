<?php

namespace App\Notifications;

/**
 * A notification type the bell can show. The message is built from the stored snapshot when it is read, so old rows
 * keep showing even after their records change.
 */
interface ShowsInInbox
{
    /** The stable `notifications.type` (ADR-0006), e.g. `reservation.created`. */
    public static function type(): string;

    /**
     * @param  array<string, mixed>  $data  the stored snapshot (notifications.data)
     * @return array{title: string, body: string, url: string|null}
     */
    public static function inboxMessage(array $data): array;
}
