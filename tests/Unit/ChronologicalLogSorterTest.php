<?php

namespace Tests\Unit;

use App\Domain\Cargo\Services\ChronologicalLogSorter;
use App\Domain\Cargo\ValueObjects\DischargeLogEntry;
use PHPUnit\Framework\TestCase;

class ChronologicalLogSorterTest extends TestCase
{
    public function test_sorts_by_occurred_at(): void
    {
        $sorter = new ChronologicalLogSorter();

        $logs = [
            new DischargeLogEntry(new \DateTimeImmutable('2026-01-01 10:03:00'), '3', 3),
            new DischargeLogEntry(new \DateTimeImmutable('2026-01-01 10:01:00'), '1', 1),
            new DischargeLogEntry(new \DateTimeImmutable('2026-01-01 10:02:00'), '2', 2),
        ];

        $sorted = $sorter->sort($logs);

        $this->assertSame('10:01:00', $sorted[0]->occurredAt->format('H:i:s'));
        $this->assertSame('10:02:00', $sorted[1]->occurredAt->format('H:i:s'));
        $this->assertSame('10:03:00', $sorted[2]->occurredAt->format('H:i:s'));
    }

    public function test_equal_timestamps_use_sequence(): void
    {
        $sorter = new ChronologicalLogSorter();
        $time = new \DateTimeImmutable('2026-01-01 10:00:00');

        $logs = [
            new DischargeLogEntry($time, 'B', 2),
            new DischargeLogEntry($time, 'A', 1),
        ];

        $sorted = $sorter->sort($logs);

        $this->assertSame('A', $sorted[0]->sequence);
        $this->assertSame('B', $sorted[1]->sequence);
    }

    public function test_does_not_mutate_input(): void
    {
        $sorter = new ChronologicalLogSorter();

        $logs = [
            new DischargeLogEntry(new \DateTimeImmutable('2026-01-01 10:02:00'), '2', 2),
            new DischargeLogEntry(new \DateTimeImmutable('2026-01-01 10:01:00'), '1', 1),
        ];

        $original = $logs;
        $sorter->sort($logs);

        $this->assertSame($original, $logs);
    }
}
