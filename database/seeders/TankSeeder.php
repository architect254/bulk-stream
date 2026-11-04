<?php

namespace Database\Seeders;

use App\Enums\TankStatus;
use App\Models\Commodity;
use App\Models\Tank;
use Illuminate\Database\Seeder;

class TankSeeder extends Seeder
{
    public function run(): void
    {
        $tanks = [
            ['code' => 'T-101', 'commodity' => 'CPO', 'capacity' => 30000],
            ['code' => 'T-102', 'commodity' => 'CPO', 'capacity' => 25000],
            ['code' => 'T-201', 'commodity' => 'CPOL', 'capacity' => 20000],
            ['code' => 'T-301', 'commodity' => 'PFAD', 'capacity' => 15000],
        ];

        foreach ($tanks as $tank) {
            $commodity = Commodity::where('code', $tank['commodity'])->firstOrFail();

            $tankModel = Tank::updateOrCreate(
                ['code' => $tank['code']],
                [
                    'commodity_id' => $commodity->id,
                    'capacity' => $tank['capacity'],
                ],
            );

            $tankModel->current_quantity = 0;
            $tankModel->status = TankStatus::Active;
            $tankModel->save();
        }
    }
}
