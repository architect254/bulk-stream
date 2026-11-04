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
    ];

    protected $attributes = [
        'status' => 'scheduled',
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

    protected static function booted(): void
    {
        static::deleting(function (self $vessel): void {
            if ($vessel->discharges()->exists()) {
                throw new \App\Domain\Cargo\Exceptions\InvalidCargoState(
                    'Vessels with discharge history cannot be deleted.'
                );
            }
        });
    }
}
