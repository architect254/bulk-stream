<?php

namespace Database\Seeders;

use App\Enums\CommodityCode;
use App\Models\Commodity;
use Illuminate\Database\Seeder;

class CommoditySeeder extends Seeder
{
    public function run(): void
    {
        $commodities = [
            ['code' => CommodityCode::CPO, 'name' => 'Crude Palm Oil'],
            ['code' => CommodityCode::CPOL, 'name' => 'Crude Palm Olein'],
            ['code' => CommodityCode::PFAD, 'name' => 'Palm Fatty Acid Distillate'],
        ];

        foreach ($commodities as $commodity) {
            Commodity::updateOrCreate(
                ['code' => $commodity['code']->value],
                ['name' => $commodity['name'], 'active' => true],
            );
        }
    }
}
