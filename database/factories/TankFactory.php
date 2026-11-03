<?php

namespace Database\Factories;

use App\Enums\TankStatus;
use App\Models\Commodity;
use App\Models\Tank;
use Illuminate\Database\Eloquent\Factories\Factory;

class TankFactory extends Factory
{
    protected $model = Tank::class;

    public function definition(): array
    {
        return [
            'code' => 'T-'.fake()->unique()->numerify('###'),
            'commodity_id' => Commodity::factory(),
            'capacity' => 30000,
            'current_quantity' => 0,
            'status' => TankStatus::Active,
        ];
    }
}
