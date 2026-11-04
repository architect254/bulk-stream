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
            $vessel = Vessel::updateOrCreate(['name' => $name]);
            $vessel->status = VesselStatus::Scheduled;
            $vessel->save();
        }
    }
}
