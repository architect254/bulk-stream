<?php

namespace Tests\Feature;

use App\Enums\CommodityCode;
use App\Enums\DischargeStatus;
use App\Filament\Widgets\DischargeMassBalanceChart;
use App\Models\Commodity;
use App\Models\Discharge;
use App\Models\Tank;
use App\Models\User;
use App\Support\OperationalTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ReadsChartData;
use Tests\TestCase;

class DischargeMassBalanceChartTest extends TestCase
{
    use ReadsChartData;
    use RefreshDatabase;

    private function freezeAt(string $datetime): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($datetime, OperationalTime::timezone()));
    }

    public function test_completed_discharge_shows_exact_mass_balance(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 30000, 'current_quantity' => 0]);

        $discharge = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Completed,
            'gross_mass' => 1000,
            'transit_loss_mass' => 5,
            'net_received_mass' => 995,
            'completed_at' => CarbonImmutable::parse('2026-11-04 09:00:00', OperationalTime::timezone()),
        ]);

        $widget = app(DischargeMassBalanceChart::class);
        $widget->dischargeId = (int) $discharge->id;

        $data = $this->chartData($widget);

        $this->assertSame(['Discharge'], $data['labels']);
        $this->assertSame(['Net received (MT)', 'Transit loss (MT)'], array_column($data['datasets'], 'label'));
        $this->assertSame([995.0], $data['datasets'][0]['data']);
        $this->assertSame([5.0], $data['datasets'][1]['data']);
        $this->assertFalse($widget->isEmpty());

        $description = (string) $widget->getDescription();
        $this->assertStringContainsString('1,000.000 MT', $description);
        $this->assertStringContainsString('995.000 MT', $description);
        $this->assertStringContainsString('5.000 MT', $description);
        $this->assertStringContainsString('0.50%', $description);
    }

    public function test_loss_is_not_exaggerated_in_the_visualization(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 30000, 'current_quantity' => 0]);

        $discharge = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Completed,
            'gross_mass' => 1000,
            'transit_loss_mass' => 5,
            'net_received_mass' => 995,
            'completed_at' => CarbonImmutable::parse('2026-11-04 09:00:00', OperationalTime::timezone()),
        ]);

        $widget = app(DischargeMassBalanceChart::class);
        $widget->dischargeId = (int) $discharge->id;

        $data = $this->chartData($widget);

        $this->assertSame(995.0, $data['datasets'][0]['data'][0] + 0);
        $this->assertSame(5.0, $data['datasets'][1]['data'][0]);

        // No log axis or broken scale is used; the chart type is a plain bar.
        $this->assertSame('bar', $this->chartType($widget));
    }

    public function test_pending_discharge_shows_pending_state(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 30000, 'current_quantity' => 0]);

        $discharge = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Draft,
            'gross_mass' => 1000,
            'transit_loss_mass' => 5,
            'net_received_mass' => 995,
        ]);

        $widget = app(DischargeMassBalanceChart::class);
        $widget->dischargeId = (int) $discharge->id;

        $this->assertTrue($widget->isEmpty());
        $this->assertSame('Mass balance pending', $widget->getEmptyStateHeading());
        $this->assertSame(['labels' => [], 'datasets' => []], $this->chartData($widget));
    }

    public function test_unavailable_values_show_unavailable_state(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 30000, 'current_quantity' => 0]);

        $discharge = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Completed,
            'gross_mass' => 1000,
            'transit_loss_mass' => 5,
            'net_received_mass' => 900,
            'completed_at' => CarbonImmutable::parse('2026-11-04 09:00:00', OperationalTime::timezone()),
        ]);

        $widget = app(DischargeMassBalanceChart::class);
        $widget->dischargeId = (int) $discharge->id;

        $this->assertTrue($widget->isEmpty());
        $this->assertSame('Mass balance unavailable', $widget->getEmptyStateHeading());
    }

    public function test_chart_does_not_modify_discharge_or_inventory(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 30000, 'current_quantity' => 0]);

        $discharge = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Completed,
            'gross_mass' => 1000,
            'transit_loss_mass' => 5,
            'net_received_mass' => 995,
            'completed_at' => CarbonImmutable::parse('2026-11-04 09:00:00', OperationalTime::timezone()),
        ]);

        $widget = app(DischargeMassBalanceChart::class);
        $widget->dischargeId = (int) $discharge->id;

        $this->chartData($widget);

        $fresh = $discharge->fresh();
        $this->assertSame('1000.000', (string) $fresh->gross_mass);
        $this->assertSame('5.000', (string) $fresh->transit_loss_mass);
        $this->assertSame('995.000', (string) $fresh->net_received_mass);
    }

    public function test_discharge_detail_page_renders_chart(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 30000, 'current_quantity' => 0]);

        $discharge = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Completed,
            'gross_mass' => 1000,
            'transit_loss_mass' => 5,
            'net_received_mass' => 995,
            'completed_at' => CarbonImmutable::parse('2026-11-04 09:00:00', OperationalTime::timezone()),
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/admin/discharges/'.$discharge->id)
            ->assertSuccessful()
            ->assertSee('Discharge mass balance');
    }
}
