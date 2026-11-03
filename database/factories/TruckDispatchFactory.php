<?php

namespace Database\Factories;

use App\Enums\TruckDispatchStatus;
use App\Models\Commodity;
use App\Models\Tank;
use App\Models\TruckDispatch;
use Illuminate\Database\Eloquent\Factories\Factory;

class TruckDispatchFactory extends Factory
{
    protected $model = TruckDispatch::class;

    public function definition(): array
    {
        return [
            'reference' => 'TD-'.fake()->unique()->numerify('####'),
            'truck_registration' => 'K'.fake()->bothify('?? ###?'),
            'driver_name' => fake()->name(),
            'commodity_id' => Commodity::factory(),
            'tank_id' => Tank::factory(),
            'quantity' => 100,
            'status' => TruckDispatchStatus::Planned,
            'dispatched_at' => null,
            'idempotency_key' => null,
        ];
    }
}
