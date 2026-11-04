<?php

namespace Tests\Feature;

use App\Actions\ProcessInboundDischarge;
use App\Application\Dashboard\TerminalDashboardReadModel;
use App\Support\OperationalTime;
use App\Enums\CommodityCode;
use App\Enums\DischargeStatus;
use App\Enums\TankStatus;
use App\Enums\TruckDispatchStatus;
use App\Enums\VesselStatus;
use App\Filament\Pages\Dashboard;
use App\Models\Commodity;
use App\Models\Discharge;
use App\Models\DischargeLog;
use App\Models\InventoryMovement;
use App\Models\Tank;
use App\Models\TruckDispatch;
use App\Models\User;
use App\Models\Vessel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_guest_is_redirected_to_the_login_page(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_dashboard_renders_terminal_heading_and_reporting_date(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        Livewire::actingAs(User::factory()->create())
            ->test(Dashboard::class)
            ->assertSuccessful()
            ->assertSee('Bulkstream Cargo Handling System')
            ->assertSee('Mombasa Terminal')
            ->assertSee('Wednesday, 4 November 2026')
            ->assertSee('Africa/Nairobi');
    }

    public function test_dashboard_shows_received_and_dispatched_totals_for_today(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 5000,
            'current_quantity' => 0,
        ]);
        $vessel = Vessel::factory()->create(['status' => VesselStatus::Arriving]);

        $discharge = Discharge::factory()->create([
            'vessel_id' => $vessel->id,
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Draft,
        ]);
        DischargeLog::factory()->create([
            'discharge_id' => $discharge->id,
            'external_event_id' => 'dashboard-inbound',
            'occurred_at' => now()->subMinute(),
            'gross_mass' => 1000,
        ]);

        app(ProcessInboundDischarge::class)->execute($discharge->fresh());

        Livewire::actingAs(User::factory()->create())
            ->test(Dashboard::class)
            ->assertSuccessful()
            ->assertSee('Received today (MT)')
            ->assertSee('995.000')
            ->assertSee('1,000.000 MT gross')
            ->assertSee('5.000 MT transit loss')
            ->assertSee('Current inventory (MT)')
            ->assertSee('Available capacity (MT)');
    }

    public function test_dashboard_lists_pending_operational_work(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 5000,
            'current_quantity' => 500,
        ]);
        $vessel = Vessel::factory()->create(['status' => VesselStatus::Arriving]);

        $discharge = Discharge::factory()->create([
            'reference' => 'DIS-ATTENTION',
            'vessel_id' => $vessel->id,
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Draft,
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(Dashboard::class)
            ->assertSuccessful()
            ->assertSee('Operational attention')
            ->assertSee('Draft discharges awaiting processing')
            ->assertSee($discharge->reference)
            ->assertSee('Vessels awaiting discharge');
    }

    public function test_dashboard_shows_a_calm_empty_state_when_nothing_needs_attention(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Dashboard::class)
            ->assertSuccessful()
            ->assertSee('No operational exceptions');
    }

    public function test_dashboard_renders_tank_board_and_recent_movements(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'code' => 'T-101',
            'commodity_id' => $commodity->id,
            'capacity' => 30000,
            'current_quantity' => 7500,
            'status' => TankStatus::Active,
        ]);
        TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => TruckDispatchStatus::Planned,
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(Dashboard::class)
            ->assertSuccessful()
            ->assertSee('Tank inventory')
            ->assertSee('T-101')
            ->assertSee('25.00%')
            ->assertSee('Recent inventory movements');
    }

    public function test_dashboard_widgets_use_the_shared_request_scoped_read_model(): void
    {
        $first = app(TerminalDashboardReadModel::class);
        $second = app(TerminalDashboardReadModel::class);

        $this->assertSame($first, $second);
    }

    public function test_near_midnight_utc_timestamp_is_presented_in_nairobi(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-04 22:30:00', 'UTC'));

        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 5000,
            'current_quantity' => 0,
        ]);

        $movement = InventoryMovement::factory()->create([
            'tank_id' => $tank->id,
            'commodity_id' => $commodity->id,
            'movement_type' => \App\Enums\InventoryMovementType::Inbound,
            'quantity' => 10,
            'balance_before' => 0,
            'balance_after' => 10,
            'occurred_at' => CarbonImmutable::parse('2026-11-04 22:30:00', 'UTC'),
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(Dashboard::class)
            ->assertSuccessful()
            ->assertSee('Nov 5, 2026 01:30:00');

        $this->assertSame(
            '2026-11-04 22:30:00',
            (string) \Illuminate\Support\Facades\DB::table('inventory_movements')->where('id', $movement->id)->value('occurred_at')
        );
    }

    private function freezeAt(string $datetime): void
    {
        CarbonImmutable::setTestNow(
            CarbonImmutable::parse($datetime, OperationalTime::timezone())
        );
    }
}
