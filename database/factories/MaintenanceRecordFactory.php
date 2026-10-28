<?php

namespace Database\Factories;

use App\Enums\MaintenanceKind;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\MaintenanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Preventive maintenance completed yesterday. The maintenance Actions (RF_10, RF_11, RF_26) are not built yet, so
 * reports and statistics (RF_66, RF_82) use this as their fixture: `MaintenanceRecordFactory::new()->repair()->create()`.
 *
 * @extends Factory<MaintenanceRecord>
 */
class MaintenanceRecordFactory extends Factory
{
    protected $model = MaintenanceRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'kind' => MaintenanceKind::Preventive,
            'status' => MaintenanceStatus::Completed,
            'started_on' => now()->subDay()->toDateString(),
            'completed_on' => now()->subDay()->toDateString(),
            'description' => fake()->sentence(),
            'performed_by' => fake()->name(),
            'registered_by' => User::factory(),
        ];
    }

    /** Corrective maintenance, which the reports call a repair. */
    public function repair(): static
    {
        return $this->state(['kind' => MaintenanceKind::Corrective]);
    }

    public function completedOn(string $date): static
    {
        return $this->state(['started_on' => $date, 'completed_on' => $date]);
    }

    public function scheduled(): static
    {
        return $this->state(['status' => MaintenanceStatus::Scheduled, 'scheduled_for' => now()->addWeek()->toDateString(),
            'started_on' => null, 'completed_on' => null]);
    }
}
