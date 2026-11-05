<?php

namespace App\Models;

use App\Domain\Cargo\Exceptions\InvalidCargoState;
use App\Enums\TruckDispatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TruckDispatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'truck_registration',
        'driver_name',
        'commodity_id',
        'tank_id',
        'quantity',
        'dispatched_at',
        'idempotency_key',
    ];

    protected $attributes = [
        'status' => 'planned',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'status' => TruckDispatchStatus::class,
            'dispatched_at' => 'datetime',
        ];
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }

    public function tank(): BelongsTo
    {
        return $this->belongsTo(Tank::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'source_id')
            ->where('source_type', self::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $dispatch): void {
            $registration = trim((string) $dispatch->truck_registration);
            $driver = trim((string) $dispatch->driver_name);

            if ($registration === '' || strlen($registration) < 3 || strlen($registration) > 32
                || ! preg_match('/^[A-Z0-9][A-Z0-9 \-]{2,31}$/i', $registration)) {
                throw new InvalidCargoState('Truck registration must be a valid commercial registration.');
            }

            if ($driver === '' || strlen($driver) < 2 || strlen($driver) > 255) {
                throw new InvalidCargoState('Driver name must be between 2 and 255 characters.');
            }

            $dispatch->truck_registration = $registration;
            $dispatch->driver_name = $driver;
        });

        static::creating(function (self $dispatch): void {
            if (blank($dispatch->reference)) {
                $dispatch->reference = 'TRK-'.now()->format('Y').'-'.Str::upper(Str::random(8));
            }
        });

        static::deleting(function (self $dispatch): void {
            if ($dispatch->status === TruckDispatchStatus::Dispatched) {
                throw new InvalidCargoState('Dispatched truck deliveries cannot be deleted.');
            }
        });
    }
}
