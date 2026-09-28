<?php

namespace Database\Factories;

use App\Models\FleetSnapshot;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FleetSnapshot>
 */
class FleetSnapshotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'snapshot_date' => now()->toDateString(),
            'sites_total' => fake()->numberBetween(1, 20),
            'sites_connected' => fake()->numberBetween(1, 20),
            'avg_security_score' => fake()->randomFloat(1, 40, 100),
            'updates_pending' => fake()->numberBetween(0, 40),
            'vulns_open' => fake()->numberBetween(0, 20),
            'vulns_critical' => fake()->numberBetween(0, 3),
            'vulns_high' => fake()->numberBetween(0, 5),
            'uptime_avg_pct' => fake()->randomFloat(2, 95, 100),
        ];
    }
}
