<?php

namespace Tests\Feature;

use App\Enums\CommodityCode;
use App\Enums\TruckDispatchStatus;
use App\Models\Commodity;
use App\Models\Tank;
use App\Models\TruckDispatch;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    public function test_concurrent_truck_dispatches_respect_inventory(): void
    {
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('proc_open is not available.');
        }

        $databasePath = '/tmp/bulkstream-concurrency.sqlite';
        @unlink($databasePath);
        touch($databasePath);

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => $databasePath]);
        DB::purge('sqlite');

        $this->artisan('migrate')->assertExitCode(0);

        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 1000,
            'current_quantity' => 1000,
        ]);

        $dispatchOne = TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'quantity' => 600,
            'status' => TruckDispatchStatus::Planned,
            'truck_registration' => 'KAA 111A',
            'driver_name' => 'Driver One',
        ]);

        $dispatchTwo = TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'quantity' => 600,
            'status' => TruckDispatchStatus::Planned,
            'truck_registration' => 'KAA 222B',
            'driver_name' => 'Driver Two',
        ]);

        $script = <<<'PHP'
<?php
require '/home/jared/PhpstormProjects/bulk-stream/vendor/autoload.php';
$app = require '/home/jared/PhpstormProjects/bulk-stream/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dispatchId = (int) ($argv[1] ?? 0);

try {
    $dispatch = App\Models\TruckDispatch::findOrFail($dispatchId);
    app(App\Actions\ProcessTruckDispatch::class)->execute($dispatch->fresh(['tank', 'commodity']));
    fwrite(STDOUT, "SUCCESS\n");
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage()."\n");
    exit(1);
}
PHP;

        file_put_contents('/tmp/process-dispatch-concurrency.php', $script);

        $php = '/home/jared/bin/php';
        $processes = [];

        foreach ([$dispatchOne->id, $dispatchTwo->id] as $dispatchId) {
            $pipes = [];
            $process = proc_open(
                [$php, '/tmp/process-dispatch-concurrency.php', (string) $dispatchId],
                [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ],
                $pipes,
                null,
                [
                    'APP_ENV' => 'testing',
                    'DB_CONNECTION' => 'sqlite',
                    'DB_DATABASE' => $databasePath,
                    'DB_URL' => '',
                    'CACHE_STORE' => 'array',
                    'QUEUE_CONNECTION' => 'sync',
                    'SESSION_DRIVER' => 'array',
                ]
            );

            $processes[] = [
                'process' => $process,
                'pipes' => $pipes,
            ];
        }

        foreach ($processes as &$processData) {
            fclose($processData['pipes'][0]);
            $processData['stdout'] = stream_get_contents($processData['pipes'][1]);
            $processData['stderr'] = stream_get_contents($processData['pipes'][2]);
            fclose($processData['pipes'][1]);
            fclose($processData['pipes'][2]);
            $processData['exit_code'] = proc_close($processData['process']);
        }
        unset($processData);

        try {
            $successes = count(array_filter($processes, fn (array $process): bool => $process['exit_code'] === 0));
            $failures = count($processes) - $successes;

            $this->assertSame(1, $successes, implode("\n", array_column($processes, 'stderr')));
            $this->assertSame(1, $failures);
            $this->assertSame('400.000', (string) Tank::find($tank->id)->current_quantity);
            $this->assertSame(1, \App\Models\InventoryMovement::where('tank_id', $tank->id)->count());
            $this->assertSame(1, TruckDispatch::where('status', TruckDispatchStatus::Dispatched)->count());
        } finally {
            \App\Models\InventoryMovement::where('tank_id', $tank->id)->delete();
            TruckDispatch::whereIn('id', [$dispatchOne->id, $dispatchTwo->id])->delete();
            Tank::where('id', $tank->id)->delete();
            Commodity::where('id', $commodity->id)->delete();
            @unlink($databasePath);
        }
    }
}
