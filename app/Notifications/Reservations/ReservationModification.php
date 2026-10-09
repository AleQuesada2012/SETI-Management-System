<?php

namespace App\Notifications\Reservations;

use App\Models\Space;
use Carbon\CarbonInterface;

/**
 * RF_49 · What a modification notification adds to the snapshot: where and when the reservation was before the
 * change, and the optional reason the user gave. `space` and `periods` stay the reservation as it is now.
 */
abstract class ReservationModification extends ReservationNotification
{
    public function withPrevious(Space $space, CarbonInterface $startsAt, CarbonInterface $endsAt, ?string $reason): static
    {
        return new static([
            ...$this->snapshot,
            'previous' => [
                'space' => ['id' => $space->id, 'code' => $space->code, 'name' => $space->name],
                'periods' => [['starts_at' => $startsAt->toIso8601String(), 'ends_at' => $endsAt->toIso8601String()]],
            ],
            'reason' => $reason,
        ]);
    }

    /**
     * The parts of messageParts() plus the previous space and block, and " Motivo: …" when a reason was given.
     *
     * @param  array<string, mixed>  $data
     * @return array{space: string, periods: string, title: string, actor: string, previous_space: string, previous_periods: string, reason: string}
     */
    protected static function modificationParts(array $data): array
    {
        $previous = self::messageParts([...$data, ...($data['previous'] ?? [])]);
        $reason = (string) ($data['reason'] ?? '');

        return [
            ...self::messageParts($data),
            'previous_space' => $previous['space'],
            'previous_periods' => $previous['periods'],
            'reason' => $reason === '' ? '' : ' '.__('reservations.notifications.reason', ['reason' => $reason]),
        ];
    }
}
