<?php

namespace App\Models;

use App\Domain\Cargo\Exceptions\InvalidCargoState;
use App\Domain\Cargo\Exceptions\InvalidChronology;
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

    protected static function booted(): void
    {
        static::saving(function (self $log): void {
            $eventId = trim((string) $log->external_event_id);

            if ($eventId === '' || strlen($eventId) > 255) {
                throw new InvalidCargoState('External event ID must be between 1 and 255 characters.');
            }

            $log->external_event_id = $eventId;

            if ($log->occurred_at !== null && $log->occurred_at->isFuture()) {
                throw new InvalidChronology('Source event timestamp cannot be in the future.');
            }

        });
    }
}
