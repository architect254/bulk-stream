<?php

namespace App\Models;

use App\Domain\Cargo\Exceptions\InvalidCargoState;
use App\Enums\DischargeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Discharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'vessel_id',
        'commodity_id',
        'tank_id',
        'gross_mass',
        'transit_loss_mass',
        'net_received_mass',
        'started_at',
        'completed_at',
        'idempotency_key',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'status' => DischargeStatus::class,
            'gross_mass' => 'decimal:3',
            'transit_loss_mass' => 'decimal:3',
            'net_received_mass' => 'decimal:3',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }

    public function tank(): BelongsTo
    {
        return $this->belongsTo(Tank::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DischargeLog::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'source_id')
            ->where('source_type', self::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $discharge): void {
            if (blank($discharge->reference)) {
                $discharge->reference = 'DIS-'.now()->format('Y').'-'.Str::upper(Str::random(8));
            }
        });

        static::deleting(function (self $discharge): void {
            if ($discharge->status !== DischargeStatus::Draft) {
                throw new InvalidCargoState('Processed discharges cannot be deleted.');
            }

            if (InventoryMovement::query()
                ->where('source_type', self::class)
                ->where('source_id', $discharge->id)
                ->exists()) {
                throw new InvalidCargoState('Discharges with inventory effects cannot be deleted.');
            }
        });
    }
}
