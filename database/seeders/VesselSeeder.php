<?php

namespace Database\Seeders;

use App\Enums\VesselStatus;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

class VesselSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['MV Ocean Meridian', 'MV Coast Pioneer', 'MV Eastern Horizon'] as $name) {
            Vessel::updateOrCreate(
                ['name' => $name],
                ['status' => VesselStatus::Scheduled],
            );
        }
    }
}
