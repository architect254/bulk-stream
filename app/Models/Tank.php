<?php

namespace App\Models;

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
        'current_quantity',
        'status',
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
        return bcsub((string) $this->capacity, (string) $this->current_quantity, 3);
    }
}
