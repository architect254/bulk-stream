<?php

namespace App\Models;

use App\Domain\Cargo\Exceptions\InvalidCargoState;
use App\Enums\TruckDispatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    protected static function booted(): void
    {
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
