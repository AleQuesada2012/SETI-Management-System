<?php

namespace Database\Factories;

use App\Enums\MovementType;
use App\Models\Asset;
use App\Models\AssetCondition;
use App\Models\AssetMovement;
use App\Models\AssetType;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Creates an asset WITHOUT a location. Use ->inSpace($space) to place it: that inserts an `entry`
 * movement, the only way to set a location (ADR-0003). Needs the seeded catalogs.
 *
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_tag' => fake()->unique()->bothify('TEC-#####'),
            'serial_number' => fake()->bothify('SN-????-########'),
            'asset_type_id' => fn () => AssetType::query()->where('name', 'Monitor')->value('id'),
            'model' => fake()->bothify('Modelo ###'),
            'condition_id' => fn () => AssetCondition::query()->where('name', 'Buen estado')->value('id'),
            'acquired_on' => fake()->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
        ];
    }

    public function ofType(string $typeName): static
    {
        return $this->state(fn (array $attributes) => [
            'asset_type_id' => AssetType::query()->where('name', $typeName)->value('id'),
        ]);
    }

    public function inCondition(string $conditionName): static
    {
        return $this->state(fn (array $attributes) => [
            'condition_id' => AssetCondition::query()->where('name', $conditionName)->value('id'),
        ]);
    }

    /** Place the asset in a space through an `entry` movement. */
    public function inSpace(Space $space): static
    {
        return $this->afterCreating(function (Asset $asset) use ($space): void {
            AssetMovement::query()->create([
                'asset_id' => $asset->id,
                'type' => MovementType::Entry,
                'to_space_id' => $space->id,
                'moved_at' => now()->subSecond(),
                'reason' => 'Registro inicial',
            ]);
            $asset->refresh();
        });
    }
}
