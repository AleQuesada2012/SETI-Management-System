<?php

namespace App\Notifications\Reservations;

use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use App\Notifications\ShowsInInbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * What a reservation notification carries (ADR-0006, Agent-DB.md §10): a snapshot of the space, the booked periods,
 * the reason and who acted, taken when it is sent, so it keeps saying what was true then. The ids link to the
 * reservations. One notification per action: a whole series is one notification that lists all its periods.
 *
 * Queued after the commit, so a rolled-back operation never notifies anyone. Stored in `notifications` and pushed
 * over Reverb on the recipient's private channel (App.Models.User.{id}).
 *
 * @phpstan-type Snapshot array{
 *     reservation_ids: list<int>,
 *     series_id: string|null,
 *     space: array{id: int, code: string, name: string},
 *     periods: list<array{starts_at: string, ends_at: string}>,
 *     title: string,
 *     actor: array{id: int, name: string},
 * }
 */
abstract class ReservationNotification extends Notification implements ShouldQueueAfterCommit, ShowsInInbox
{
    use Queueable;

    /**
     * @param  Snapshot  $snapshot
     */
    final public function __construct(public readonly array $snapshot) {}

    /** The stable `notifications.type` (ADR-0006): renaming the class never breaks old rows. */
    abstract public static function type(): string;

    /**
     * @param  list<Reservation>  $reservations  the reservations of one action, all in the same space
     */
    public static function of(Space $space, array $reservations, User $actor): static
    {
        return new static([
            'reservation_ids' => array_map(fn (Reservation $reservation): int => $reservation->id, $reservations),
            'series_id' => $reservations[0]->series_id,
            'space' => ['id' => $space->id, 'code' => $space->code, 'name' => $space->name],
            'periods' => array_map(fn (Reservation $reservation): array => [
                'starts_at' => $reservation->starts_at->toIso8601String(),
                'ends_at' => $reservation->ends_at->toIso8601String(),
            ], $reservations),
            'title' => $reservations[0]->title,
            'actor' => ['id' => $actor->id, 'name' => $actor->name],
        ]);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function databaseType(object $notifiable): string
    {
        return static::type();
    }

    public function broadcastType(): string
    {
        return static::type();
    }

    /**
     * @return Snapshot
     */
    public function toArray(object $notifiable): array
    {
        return $this->snapshot;
    }

    /**
     * "LAB-110 · Laboratorio 110" and "15/10/2030, de 10:00 a 12:00; 22/10/2030, de 10:00 a 12:00": the parts every
     * reservation message names, in the application's time zone.
     *
     * @param  array<string, mixed>  $data
     * @return array{space: string, periods: string, title: string, actor: string}
     */
    protected static function messageParts(array $data): array
    {
        $periods = array_map(function (array $period): string {
            $startsAt = Carbon::parse($period['starts_at'])->setTimezone(config('app.timezone'));
            $endsAt = Carbon::parse($period['ends_at'])->setTimezone(config('app.timezone'));

            return __('reservations.notifications.period', [
                'date' => $startsAt->format('d/m/Y'),
                'start' => $startsAt->format('H:i'),
                'end' => $endsAt->format('H:i'),
            ]);
        }, $data['periods'] ?? []);

        return [
            'space' => "{$data['space']['code']} · {$data['space']['name']}",
            'periods' => implode('; ', $periods),
            'title' => (string) ($data['title'] ?? ''),
            'actor' => (string) ($data['actor']['name'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function reservationUrl(array $data): ?string
    {
        $id = $data['reservation_ids'][0] ?? null;

        return $id === null ? null : route('reservations.show', $id);
    }
}
