<?php

namespace Database\Factories;

use App\Enums\VesselStatus;
use App\Models\Vessel;
use Illuminate\Database\Eloquent\Factories\Factory;

class VesselFactory extends Factory
{
    protected $model = Vessel::class;

    public function definition(): array
    {
        return [
            'name' => 'MV '.fake()->lastName(),
            'voyage_reference' => 'VOY-'.fake()->numerify('####'),
            'status' => VesselStatus::Scheduled,
        ];
    }
}
