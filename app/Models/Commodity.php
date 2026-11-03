<?php

namespace App\Models;

use App\Enums\CommodityCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commodity extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'code' => CommodityCode::class,
            'active' => 'boolean',
        ];
    }

    public function tanks(): HasMany
    {
        return $this->hasMany(Tank::class);
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
}
