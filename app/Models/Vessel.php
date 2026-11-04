<?php

namespace App\Models;

use App\Domain\Cargo\Exceptions\InvalidCargoState;
use App\Domain\Cargo\Exceptions\InvalidStateTransition;
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

    public function markAsArriving(): void
    {
        if ($this->status !== VesselStatus::Scheduled) {
            throw new InvalidStateTransition(
                'vessel',
                $this->status->value,
                VesselStatus::Arriving->value,
            );
        }

        $this->forceFill(['status' => VesselStatus::Arriving])->save();
    }

    protected static function booted(): void
    {
        static::deleting(function (self $vessel): void {
            if ($vessel->discharges()->exists()) {
                throw new InvalidCargoState(
                    'Vessels with discharge history cannot be deleted.'
                );
            }
        });
    }
}
