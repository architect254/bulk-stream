<?php

namespace Tests\Feature;

use App\Actions\ProcessInboundDischarge;
use App\Actions\ProcessTruckDispatch;
use App\Domain\Cargo\Support\DecimalMath;
use App\Enums\CommodityCode;
use App\Enums\DischargeStatus;
use App\Enums\InventoryMovementType;
use App\Enums\VesselStatus;
use App\Models\Commodity;
use App\Models\Discharge;
use App\Models\DischargeLog;
use App\Models\InventoryMovement;
use App\Models\Tank;
use App\Models\TruckDispatch;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndToEndCargoWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_vessel_to_truck_workflow(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 1100,
            'current_quantity' => 100,
        ]);
        $vessel = Vessel::factory()->create(['status' => VesselStatus::Arriving]);

        $discharge = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'vessel_id' => $vessel->id,
            'status' => DischargeStatus::Draft,
        ]);

        DischargeLog::factory()->create([
            'discharge_id' => $discharge->id,
            'external_event_id' => 'evt-late',
            'occurred_at' => now()->subMinutes(1),
            'sequence' => 'SEQ-2',
            'gross_mass' => 600,
        ]);
        DischargeLog::factory()->create([
            'discharge_id' => $discharge->id,
            'external_event_id' => 'evt-early',
            'occurred_at' => now()->subMinutes(5),
            'sequence' => 'SEQ-1',
            'gross_mass' => 400,
        ]);

        $processedDischarge = app(ProcessInboundDischarge::class)->execute($discharge->fresh(['logs', 'tank', 'commodity', 'vessel']));

        $this->assertSame(DischargeStatus::Completed, $processedDischarge->status);
        $this->assertSame('1000.000', (string) $processedDischarge->gross_mass);
        $this->assertSame('5.000', (string) $processedDischarge->transit_loss_mass);
        $this->assertSame('995.000', (string) $processedDischarge->net_received_mass);

        $tankAfterInbound = Tank::find($tank->id);
        $this->assertSame('1095.000', (string) $tankAfterInbound->current_quantity);

        $inboundMovement = InventoryMovement::where('source_type', Discharge::class)->where('source_id', $processedDischarge->id)->firstOrFail();
        $this->assertSame(InventoryMovementType::Inbound, $inboundMovement->movement_type);
        $this->assertSame('995.000', (string) $inboundMovement->quantity);
        $this->assertSame('100.000', (string) $inboundMovement->balance_before);
        $this->assertSame('1095.000', (string) $inboundMovement->balance_after);

        $dispatch = TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'quantity' => 300,
            'status' => \App\Enums\TruckDispatchStatus::Planned,
        ]);

        $processedDispatch = app(ProcessTruckDispatch::class)->execute($dispatch->fresh(['tank', 'commodity']));

        $this->assertSame(\App\Enums\TruckDispatchStatus::Dispatched, $processedDispatch->status);

        $tankAfterOutbound = Tank::find($tank->id);
        $this->assertSame('795.000', (string) $tankAfterOutbound->current_quantity);

        $outboundMovement = InventoryMovement::where('source_type', TruckDispatch::class)->where('source_id', $processedDispatch->id)->firstOrFail();
        $this->assertSame(InventoryMovementType::Outbound, $outboundMovement->movement_type);
        $this->assertSame('300.000', (string) $outboundMovement->quantity);
        $this->assertSame('1095.000', (string) $outboundMovement->balance_before);
        $this->assertSame('795.000', (string) $outboundMovement->balance_after);

        $this->assertSame(2, InventoryMovement::count());
    }
}
