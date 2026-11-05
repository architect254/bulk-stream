<?php

namespace Tests\Feature;

use App\Enums\CommodityCode;
use App\Enums\DischargeStatus;
use App\Enums\TruckDispatchStatus;
use App\Models\Commodity;
use App\Models\Discharge;
use App\Models\Tank;
use App\Models\TruckDispatch;
use App\Models\User;
use App\Support\OperationalTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsChartPageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_analytics_page_renders_every_chart_and_the_transit_loss_summary(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'code' => 'T-101',
            'commodity_id' => $commodity->id,
            'capacity' => 30000,
            'current_quantity' => 0,
        ]);

        Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Completed,
            'gross_mass' => 1000,
            'transit_loss_mass' => 5,
            'net_received_mass' => 995,
            'completed_at' => CarbonImmutable::parse('2026-11-04 09:00:00', OperationalTime::timezone()),
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/admin/operations-analytics')
            ->assertSuccessful()
            ->assertSee('Received mass by day')
            ->assertSee('Dispatch volume by day')
            ->assertSee('Tank capacity utilization')
            ->assertSee('Commodity throughput and current stock')
            ->assertSee('Transit loss for this period')
            ->assertSee('0.5% of 1,000.000 MT gross received.');
    }

    public function test_analytics_charts_receive_the_selected_reporting_period(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $user = User::factory()->create();

        $today = $this->chartPayload($user, '/admin/operations-analytics?period=today', 'Gross received (MT)');
        $week = $this->chartPayload($user, '/admin/operations-analytics?period=last_7_days', 'Gross received (MT)');
        $custom = $this->chartPayload(
            $user,
            '/admin/operations-analytics?period=custom&start_date=2026-11-03&end_date=2026-11-05',
            'Gross received (MT)',
        );

        $this->assertSame(['4 Nov'], $today['labels']);
        $this->assertCount(7, $week['labels']);
        $this->assertSame('29 Oct', $week['labels'][0]);
        $this->assertSame('4 Nov', $week['labels'][6]);
        $this->assertSame(['3 Nov', '4 Nov', '5 Nov'], $custom['labels']);
    }

    public function test_dashboard_charts_use_the_last_seven_operational_days(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $response = $this->actingAs(User::factory()->create())->get('/admin');

        $response->assertSuccessful()
            ->assertSee('Cargo flow by day')
            ->assertSee('Inventory by commodity');

        $flow = $this->chartPayloadFromHtml($response->getContent(), 'Inbound net (MT)');

        $this->assertCount(7, $flow['labels']);
        $this->assertSame('4 Nov', $flow['labels'][6]);
    }

    public function test_charts_show_empty_states_when_there_is_no_operational_data(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $this->actingAs(User::factory()->create())
            ->get('/admin/operations-analytics')
            ->assertSuccessful()
            ->assertSee('No completed discharges in this period.')
            ->assertSee('No completed dispatches in this period.')
            ->assertSee('No active tanks.')
            ->assertSee('No commodity data available for this period.');

        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('No completed cargo movements in this period.')
            ->assertSee('No active tank inventory.');
    }

    public function test_dashboard_cargo_flow_reflects_completed_operations_only(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'code' => 'T-101',
            'commodity_id' => $commodity->id,
            'capacity' => 30000,
            'current_quantity' => 0,
        ]);

        Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Completed,
            'gross_mass' => 1000,
            'transit_loss_mass' => 5,
            'net_received_mass' => 995,
            'completed_at' => CarbonImmutable::parse('2026-11-04 09:00:00', OperationalTime::timezone()),
        ]);

        TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => TruckDispatchStatus::Dispatched,
            'quantity' => 100,
            'dispatched_at' => CarbonImmutable::parse('2026-11-04 10:00:00', OperationalTime::timezone()),
        ]);

        $response = $this->actingAs(User::factory()->create())->get('/admin');
        $html = $response->getContent();

        $inbound = $this->chartPayloadFromHtml($html, 'Inbound net (MT)');
        $outbound = $this->chartPayloadFromHtml($html, 'Outbound (MT)');

        $this->assertSame(995.0, (float) array_sum($inbound['datasets'][0]['data']));
        $this->assertSame(100.0, (float) array_sum($outbound['datasets'][1]['data']));
    }

    private function chartPayload(User $user, string $url, string $datasetLabel): array
    {
        $response = $this->actingAs($user)->get($url);
        $response->assertSuccessful();

        return $this->chartPayloadFromHtml($response->getContent(), $datasetLabel);
    }

    /**
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    private function chartPayloadFromHtml(string $html, string $datasetLabel): array
    {
        preg_match_all('/cachedData: JSON\.parse\(\'(.+?)\'\),/s', $html, $matches);

        foreach ($matches[1] as $encoded) {
            $decoded = json_decode(str_replace('\u0022', '"', $encoded), true);

            if (! is_array($decoded)) {
                continue;
            }

            foreach ($decoded['datasets'] ?? [] as $dataset) {
                if (($dataset['label'] ?? null) === $datasetLabel) {
                    return $decoded;
                }
            }
        }

        $this->fail('No chart payload was found for dataset ['.$datasetLabel.'].');
    }

    private function freezeAt(string $datetime): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($datetime, OperationalTime::timezone()));
    }
}
