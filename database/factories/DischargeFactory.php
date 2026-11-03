<?php

namespace Database\Factories;

use App\Enums\DischargeStatus;
use App\Models\Discharge;
use App\Models\Vessel;
use App\Models\Commodity;
use App\Models\Tank;
use Illuminate\Database\Eloquent\Factories\Factory;

class DischargeFactory extends Factory
{
    protected $model = Discharge::class;

    public function definition(): array
    {
        return [
            'reference' => 'DIS-'.fake()->unique()->numerify('####'),
            'vessel_id' => Vessel::factory(),
            'commodity_id' => Commodity::factory(),
            'tank_id' => Tank::factory(),
            'status' => DischargeStatus::Draft,
            'gross_mass' => 1000,
            'transit_loss_mass' => 5,
            'net_received_mass' => 995,
            'started_at' => now(),
            'completed_at' => null,
            'idempotency_key' => null,
        ];
    }
}
