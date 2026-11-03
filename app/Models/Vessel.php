<?php

namespace App\Models;

use App\Enums\VesselStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vessel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'voyage_reference',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => VesselStatus::class,
        ];
    }

    public function discharges(): HasMany
    {
        return $this->hasMany(Discharge::class);
    }
}
