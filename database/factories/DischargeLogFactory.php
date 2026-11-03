<?php

namespace Database\Factories;

use App\Models\Discharge;
use App\Models\DischargeLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class DischargeLogFactory extends Factory
{
    protected $model = DischargeLog::class;

    public function definition(): array
    {
        return [
            'discharge_id' => Discharge::factory(),
            'external_event_id' => (string) fake()->uuid(),
            'occurred_at' => now(),
            'sequence' => 'SEQ-'.fake()->numerify('####'),
            'gross_mass' => 100,
            'metadata' => null,
            'processed_at' => null,
        ];
    }
}
