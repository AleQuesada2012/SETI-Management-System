<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A confirmed reservation tomorrow 10:00–12:00 (Costa Rica time). Needs the seeded catalogs.
 *
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDay()->setTime(10, 0);

        return [
            'space_id' => Space::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHours(2),
            'title' => fake()->sentence(3),
            'attendees' => 5,
            'responsible_id' => User::factory(),
            'created_by' => fn (array $attributes) => $attributes['responsible_id'],
        ];
    }

    public function between(\DateTimeInterface $startsAt, \DateTimeInterface $endsAt): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }
}
