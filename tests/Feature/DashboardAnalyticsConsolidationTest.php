<?php

namespace Tests\Feature;

use App\Enums\CommodityCode;
use App\Enums\DischargeStatus;
use App\Enums\TruckDispatchStatus;
use App\Filament\Widgets\CommodityThroughputChart;
use App\Models\Commodity;
use App\Models\Discharge;
use App\Models\Tank;
use App\Models\TruckDispatch;
use App\Models\User;
use App\Support\OperationalTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ReadsChartData;
use Tests\TestCase;

class DashboardAnalyticsConsolidationTest extends TestCase
{
    use ReadsChartData;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function freezeAt(string $datetime): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($datetime, OperationalTime::timezone()));
    }

    private function completedDischarge(Commodity $commodity, Tank $tank, string $completedAt, float $gross, float $net): Discharge
    {
        return Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Completed,
            'gross_mass' => $gross,
            'transit_loss_mass' => $gross - $net,
            'net_received_mass' => $net,
            'completed_at' => CarbonImmutable::parse($completedAt, OperationalTime::timezone()),
        ]);
    }

    private function dispatchedTruck(Commodity $commodity, Tank $tank, string $dispatchedAt, float $quantity): TruckDispatch
    {
        return TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => TruckDispatchStatus::Dispatched,
            'quantity' => $quantity,
            'dispatched_at' => CarbonImmutable::parse($dispatchedAt, OperationalTime::timezone()),
        ]);
    }

    public function test_dashboard_contains_all_six_charts_and_analytical_tables(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $response = $this->actingAs(User::factory()->create())->get('/admin');

        $response->assertSuccessful()
            ->assertSee('Cargo flow by day')
            ->assertSee('Inventory by commodity')
            ->assertSee('Received mass by day')
            ->assertSee('Dispatch volume by day')
            ->assertSee('Tank capacity utilization')
            ->assertSee('Commodity throughput and current stock')
            ->assertSee('Commodity breakdown')
            ->assertSee('Completed discharges and transit loss')
            ->assertSee('Inventory reconciliation')
            ->assertSee('Tank inventory')
            ->assertSee('Recent inventory movements');
    }

    public function test_dashboard_period_filter_propagates_to_received_mass_chart(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 30000, 'current_quantity' => 0]);

        $this->completedDischarge($commodity, $tank, '2026-11-04 09:00:00', 1000, 995);
        $this->completedDischarge($commodity, $tank, '2026-11-01 09:00:00', 2000, 1990);

        $user = User::factory()->create();

        $todayHtml = $this->actingAs($user)->get('/admin?'.http_build_query(['filters' => ['period' => 'today']]))->getContent();
        $todayPayload = $this->chartPayloadFromHtml($todayHtml, 'Gross received (MT)');

        $this->assertSame(['4 Nov'], $todayPayload['labels']);
        $this->assertEquals([1000.0], $todayPayload['datasets'][0]['data']);

        $weekHtml = $this->actingAs($user)->get('/admin?'.http_build_query(['filters' => ['period' => 'last_7_days']]))->getContent();
        $weekPayload = $this->chartPayloadFromHtml($weekHtml, 'Gross received (MT)');

        $this->assertCount(7, $weekPayload['labels']);
        $this->assertSame(3000.0, (float) array_sum($weekPayload['datasets'][0]['data']));
    }

    public function test_dashboard_period_presets_work_correctly(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $user = User::factory()->create();

        $today = $this->chartPayloadFromHtml(
            $this->actingAs($user)->get('/admin?'.http_build_query(['filters' => ['period' => 'today']]))->getContent(),
            'Gross received (MT)',
        );
        $week = $this->chartPayloadFromHtml(
            $this->actingAs($user)->get('/admin?'.http_build_query(['filters' => ['period' => 'last_7_days']]))->getContent(),
            'Gross received (MT)',
        );
        $currentMonth = $this->chartPayloadFromHtml(
            $this->actingAs($user)->get('/admin?'.http_build_query(['filters' => ['period' => 'current_month']]))->getContent(),
            'Gross received (MT)',
        );
        $previousMonth = $this->chartPayloadFromHtml(
            $this->actingAs($user)->get('/admin?'.http_build_query(['filters' => ['period' => 'previous_month']]))->getContent(),
            'Gross received (MT)',
        );
        $custom = $this->chartPayloadFromHtml(
            $this->actingAs($user)->get('/admin?'.http_build_query(['filters' => ['period' => 'custom', 'startDate' => '2026-11-03', 'endDate' => '2026-11-05']]))->getContent(),
            'Gross received (MT)',
        );

        $this->assertCount(1, $today['labels']);
        $this->assertCount(7, $week['labels']);
        $this->assertCount(30, $currentMonth['labels']);
        $this->assertCount(31, $previousMonth['labels']);
        $this->assertSame(['3 Nov', '4 Nov', '5 Nov'], $custom['labels']);
    }

    public function test_dashboard_period_excludes_pending_operations(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 30000, 'current_quantity' => 0]);

        $this->completedDischarge($commodity, $tank, '2026-11-04 09:00:00', 1000, 995);

        Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Draft,
            'gross_mass' => 4000,
            'transit_loss_mass' => 20,
            'net_received_mass' => 3980,
            'completed_at' => CarbonImmutable::parse('2026-11-04 09:00:00', OperationalTime::timezone()),
        ]);

        $this->dispatchedTruck($commodity, $tank, '2026-11-04 08:00:00', 100);

        TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => TruckDispatchStatus::Planned,
            'quantity' => 900,
            'dispatched_at' => CarbonImmutable::parse('2026-11-04 09:00:00', OperationalTime::timezone()),
        ]);

        $html = $this->actingAs(User::factory()->create())
            ->get('/admin?'.http_build_query(['filters' => ['period' => 'today']]))
            ->getContent();

        $received = $this->chartPayloadFromHtml($html, 'Gross received (MT)');
        $dispatched = $this->chartPayloadFromHtml($html, 'Dispatched (MT)');

        $this->assertSame(1000.0, (float) array_sum($received['datasets'][0]['data']));
        $this->assertSame(100.0, (float) array_sum($dispatched['datasets'][0]['data']));
    }

    public function test_dashboard_renders_empty_states_for_zero_activity(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $response = $this->actingAs(User::factory()->create())->get('/admin');

        $response->assertSuccessful()
            ->assertSee('No completed discharges in this period.')
            ->assertSee('No completed dispatches in this period.')
            ->assertSee('No active tanks.')
            ->assertSee('No commodity data available for this period.');
    }

    public function test_commodity_throughput_distinguishes_period_throughput_from_current_stock(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['code' => 'T-101', 'commodity_id' => $commodity->id, 'capacity' => 30000, 'current_quantity' => 995]);

        $this->completedDischarge($commodity, $tank, '2026-11-04 09:00:00', 1000, 995);

        $widget = app(CommodityThroughputChart::class);
        $widget->period = 'today';

        $data = $this->chartData($widget);

        $this->assertSame([995.0], $data['datasets'][0]['data']);
        $this->assertSame([995.0], $data['datasets'][2]['data']);
    }

    public function test_old_operations_analytics_route_is_removed(): void
    {
        $this->get('/admin/operations-analytics')->assertNotFound();
    }

    public function test_dashboard_does_not_link_to_removed_analytics_page(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/admin');

        $response->assertSuccessful()
            ->assertDontSee('Operations analytics');
    }
}
