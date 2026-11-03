<?php

namespace Database\Factories;

use App\Enums\InventoryMovementType;
use App\Models\Commodity;
use App\Models\InventoryMovement;
use App\Models\Tank;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    public function definition(): array
    {
        return [
            'reference' => 'IM-'.fake()->unique()->numerify('####'),
            'tank_id' => Tank::factory(),
            'commodity_id' => Commodity::factory(),
            'movement_type' => InventoryMovementType::Inbound,
            'quantity' => 100,
            'balance_before' => 0,
            'balance_after' => 100,
            'source_type' => null,
            'source_id' => null,
            'idempotency_key' => null,
            'occurred_at' => now(),
        ];
    }
}
