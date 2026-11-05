<?php

namespace App\Application\Reporting;

use App\Domain\Cargo\Support\DecimalMath;
use App\Enums\DischargeStatus;
use App\Models\Discharge;
use Throwable;

final class DischargeMassBalanceReadModel
{
    /**
     * @return array{
     *     status: 'available'|'pending'|'unavailable',
     *     reason: string|null,
     *     gross_mass: string|null,
     *     transit_loss_mass: string|null,
     *     net_received_mass: string|null,
     *     loss_basis_points: int|null
     * }
     */
    public function forDischarge(Discharge $discharge): array
    {
        if ($discharge->status !== DischargeStatus::Completed) {
            return [
                'status' => 'pending',
                'reason' => 'Mass balance will be available once the discharge is completed.',
                'gross_mass' => null,
                'transit_loss_mass' => null,
                'net_received_mass' => null,
                'loss_basis_points' => null,
            ];
        }

        try {
            $grossMinor = DecimalMath::toMinorUnits((string) $discharge->gross_mass);
            $lossMinor = DecimalMath::toMinorUnits((string) $discharge->transit_loss_mass);
            $netMinor = DecimalMath::toMinorUnits((string) $discharge->net_received_mass);
        } catch (Throwable) {
            return $this->unavailable($discharge, 'The persisted discharge values are incomplete or invalid.');
        }

        if ($grossMinor <= 0 || $lossMinor < 0 || $netMinor < 0) {
            return $this->unavailable($discharge, 'The persisted discharge values cannot support a mass balance.');
        }

        if ($grossMinor !== $lossMinor + $netMinor) {
            return $this->unavailable($discharge, 'The persisted gross, transit-loss and net values do not reconcile.');
        }

        return [
            'status' => 'available',
            'reason' => null,
            'gross_mass' => DecimalMath::fromMinorUnits($grossMinor),
            'transit_loss_mass' => DecimalMath::fromMinorUnits($lossMinor),
            'net_received_mass' => DecimalMath::fromMinorUnits($netMinor),
            'loss_basis_points' => intdiv($lossMinor * 10000, $grossMinor),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailable(Discharge $discharge, string $reason): array
    {
        return [
            'status' => 'unavailable',
            'reason' => $reason,
            'gross_mass' => $discharge->gross_mass === null ? null : (string) $discharge->gross_mass,
            'transit_loss_mass' => $discharge->transit_loss_mass === null ? null : (string) $discharge->transit_loss_mass,
            'net_received_mass' => $discharge->net_received_mass === null ? null : (string) $discharge->net_received_mass,
            'loss_basis_points' => null,
        ];
    }
}
