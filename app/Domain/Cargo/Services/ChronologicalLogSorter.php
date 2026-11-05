<?php

namespace App\Domain\Cargo\Services;

use App\Domain\Cargo\ValueObjects\DischargeLogEntry;

class ChronologicalLogSorter
{
    /**
     * @param  iterable<DischargeLogEntry>  $logs
     * @return list<DischargeLogEntry>
     */
    public function sort(iterable $logs): array
    {
        $sorted = [];

        foreach ($logs as $log) {
            if (! $log instanceof DischargeLogEntry) {
                throw new \InvalidArgumentException('Logs must be DischargeLogEntry instances.');
            }

            $sorted[] = $log;
        }

        usort($sorted, static function (DischargeLogEntry $left, DischargeLogEntry $right): int {
            $comparison = $left->occurredAt <=> $right->occurredAt;

            if ($comparison !== 0) {
                return $comparison;
            }

            $sequenceComparison = strcmp((string) $left->sequence, (string) $right->sequence);

            if ($sequenceComparison !== 0) {
                return $sequenceComparison;
            }

            return ($left->id ?? 0) <=> ($right->id ?? 0);
        });

        return $sorted;
    }
}
