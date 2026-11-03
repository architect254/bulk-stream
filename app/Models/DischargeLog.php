<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DischargeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'discharge_id',
        'external_event_id',
        'occurred_at',
        'sequence',
        'gross_mass',
        'metadata',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'gross_mass' => 'decimal:3',
            'metadata' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function discharge(): BelongsTo
    {
        return $this->belongsTo(Discharge::class);
    }
}
