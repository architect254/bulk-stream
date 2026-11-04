<?php

namespace App\Models;

use App\Domain\Cargo\Support\DecimalMath;
use App\Enums\TankStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tank extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'commodity_id',
        'capacity',
    ];

    protected $attributes = [
        'current_quantity' => '0.000',
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'decimal:3',
            'current_quantity' => 'decimal:3',
            'status' => TankStatus::class,
        ];
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }

    public function discharges(): HasMany
    {
        return $this->hasMany(Discharge::class);
    }

    public function truckDispatches(): HasMany
    {
        return $this->hasMany(TruckDispatch::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function availableQuantity(): string
    {
        return DecimalMath::subtract((string) $this->capacity, (string) $this->current_quantity);
    }
    protected static function booted(): void
    {
        static::saving(function (self $tank): void {
            if ($tank->isDirty('capacity') && DecimalMath::toMinorUnits((string) $tank->capacity) < DecimalMath::toMinorUnits((string) $tank->current_quantity)) {
                throw new \App\Domain\Cargo\Exceptions\InvalidCargoQuantity('Capacity cannot be less than current inventory.');
            }

            if ($tank->isDirty('commodity_id') && $tank->exists) {
                $hasInventory = DecimalMath::toMinorUnits((string) $tank->current_quantity) > 0;
                $hasHistory = $tank->inventoryMovements()->exists()
                    || $tank->discharges()->exists()
                    || $tank->truckDispatches()->exists();

                if ($hasInventory || $hasHistory) {
                    throw new \App\Domain\Cargo\Exceptions\InvalidCargoState('Commodity cannot be changed while the tank has inventory or operational history.');
                }
            }
        });

        static::deleting(function (self $tank): void {
            if (DecimalMath::toMinorUnits((string) $tank->current_quantity) > 0
                || $tank->inventoryMovements()->exists()
                || $tank->discharges()->exists()
                || $tank->truckDispatches()->exists()) {
                throw new \App\Domain\Cargo\Exceptions\InvalidCargoState('Tank cannot be deleted while it has inventory or operational history.');
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('status', TankStatus::Active);
    }

}
