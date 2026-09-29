<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Space;
use App\Models\SpaceStatus;
use App\Models\SpaceType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Needs the seeded catalogs (CatalogSeeder): tests seed them automatically.
 *
 * @extends Factory<Space>
 */
class SpaceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 999);

        return [
            'code' => "LAB-{$number}",
            'name' => "Laboratorio {$number}",
            'space_type_id' => fn () => SpaceType::query()->where('name', 'Laboratorio')->value('id'),
            'space_status_id' => fn () => SpaceStatus::query()->where('name', 'Disponible')->value('id'),
            'building_id' => Building::factory(),
            'floor' => (string) fake()->numberBetween(1, 3),
            'capacity' => fake()->numberBetween(10, 40),
        ];
    }

    /** An office: not reservable (space_types.is_reservable = false). */
    public function office(): static
    {
        return $this->state(fn (array $attributes) => [
            'space_type_id' => SpaceType::query()->where('name', 'Oficina')->value('id'),
        ]);
    }

    public function withStatus(string $statusName): static
    {
        return $this->state(fn (array $attributes) => [
            'space_status_id' => SpaceStatus::query()->where('name', $statusName)->value('id'),
        ]);
    }

    /** Assign space managers (ADR-0009). */
    public function managedBy(User ...$managers): static
    {
        return $this->afterCreating(function (Space $space) use ($managers): void {
            $space->managers()->attach(collect($managers)->pluck('id')->all(), ['assigned_at' => now()]);
            collect($managers)->each->refreshManagedSpaces();
        });
    }
}
