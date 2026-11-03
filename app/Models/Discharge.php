<?php

namespace App\Models;

use App\Enums\DischargeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Discharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'vessel_id',
        'commodity_id',
        'tank_id',
        'status',
        'gross_mass',
        'transit_loss_mass',
        'net_received_mass',
        'started_at',
        'completed_at',
        'idempotency_key',
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
}
