<?php

namespace App\Notifications;

use App\Notifications\Reservations\ReservationCreated;
use Carbon\CarbonInterface;
use Illuminate\Notifications\DatabaseNotification;

/**
 * One notification as the bell shows it. Each type stored in `notifications.type` is registered in TYPES; a new type
 * needs only its class here, no migration (ADR-0006).
 */
final readonly class InboxEntry
{
    /** @var list<class-string<ShowsInInbox>> */
    private const array TYPES = [
        ReservationCreated::class,
    ];

    public function __construct(
        public string $id,
        public string $title,
        public string $body,
        public ?string $url,
        public bool $isUnread,
        public CarbonInterface $receivedAt,
    ) {}

    public static function from(DatabaseNotification $notification): self
    {
        /** @var string $storedType */
        $storedType = $notification->getAttribute('type');
        /** @var array<string, mixed> $data */
        $data = $notification->getAttribute('data');
        /** @var CarbonInterface $receivedAt */
        $receivedAt = $notification->getAttribute('created_at');

        $type = collect(self::TYPES)->first(fn (string $class): bool => $class::type() === $storedType);

        $message = $type === null
            ? ['title' => __('notifications.unknown'), 'body' => '', 'url' => null]
            : $type::inboxMessage($data);

        return new self(
            id: $notification->id,
            title: $message['title'],
            body: $message['body'],
            url: $message['url'],
            isUnread: $notification->unread(),
            receivedAt: $receivedAt,
        );
    }
}
