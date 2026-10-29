<?php

namespace Database\Factories;

use App\Enums\AssetEventKind;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A loss registered yesterday. It writes only the event row: the asset's lifecycle status is the writer Action's
 * job (RF_28, RF_29), so set it on the asset when a test needs it. `AssetEventFactory::new()->decommission()->create()`.
 *
 * @extends Factory<AssetEvent>
 */
class AssetEventFactory extends Factory
{
    protected $model = AssetEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'kind' => AssetEventKind::Loss,
            'occurred_on' => now()->subDay()->toDateString(),
            'description' => fake()->sentence(),
            'registered_by' => User::factory(),
        ];
    }

    public function decommission(): static
    {
        return $this->state(['kind' => AssetEventKind::Decommission]);
    }

    public function kind(AssetEventKind $kind): static
    {
        return $this->state(['kind' => $kind]);
    }
}
