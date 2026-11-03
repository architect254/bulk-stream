<?php

namespace Database\Factories;

use App\Enums\CommodityCode;
use App\Models\Commodity;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommodityFactory extends Factory
{
    protected $model = Commodity::class;

    public function definition(): array
    {
        return [
            'code' => fake()->randomElement(CommodityCode::cases())->value,
            'name' => fake()->words(2, true),
            'active' => true,
        ];
    }
}
