<?php

namespace App\Application\Reporting;

use App\Application\Inventory\LedgerReconstruction;
use App\Models\Tank;

final class TankBalanceHistoryReadModel
{
    public function __construct(
        private readonly LedgerReconstruction $ledger = new LedgerReconstruction,
    ) {}

    /**
     * @return array{
     *     status: 'verified'|'empty'|'unverifiable',
     *     reason: string|null,
     *     opening_balance: string,
     *     final_balance: string,
     *     stored_balance: string,
     *     difference: string,
     *     movement_count: int,
     *     points: list<array{movement_id:int,occurred_at:mixed,balance:string,movement_type:string,quantity:string,reference:string|null}>
     * }
     */
    public function forTank(Tank $tank): array
    {
        $evaluation = $this->ledger->evaluate($tank->inventoryMovements, (string) $tank->current_quantity);

        $status = match ($evaluation['status']) {
            'verified' => 'verified',
            'empty' => 'empty',
            default => 'unverifiable',
        };

        return [
            'status' => $status,
            'reason' => $evaluation['reason'],
            'tank_code' => $tank->code,
            'opening_balance' => $evaluation['opening_balance'],
            'final_balance' => $evaluation['reconstructed_balance'],
            'stored_balance' => $evaluation['stored_balance'],
            'difference' => $evaluation['difference'],
            'movement_count' => $evaluation['movements']->count(),
            'points' => $status === 'verified' ? $evaluation['points'] : [],
        ];
    }
}
