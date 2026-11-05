<?php

namespace Tests\Feature;

use App\Enums\CommodityCode;
use App\Enums\TruckDispatchStatus;
use App\Models\Commodity;
use App\Models\Tank;
use App\Models\TruckDispatch;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MariaDbConcurrencyTest extends TestCase
{
    public function test_mariadb_concurrent_truck_dispatches_respect_inventory(): void
    {
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('proc_open is not available.');
        }

        config(['database.default' => 'mysql']);
        config(['database.connections.mysql.host' => 'localhost']);
        config(['database.connections.mysql.port' => 3307]);
        config(['database.connections.mysql.database' => 'bulk_stream_concurrency']);
        config(['database.connections.mysql.username' => 'bulkstream']);
        config(['database.connections.mysql.password' => 'bulkstream']);
        config(['database.connections.mysql.unix_socket' => '/home/jared/mariadb-run/mysql.sock']);
        DB::purge('mysql');

        $this->artisan('migrate:fresh')->assertExitCode(0);

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

        foreach (['/tmp/conc-ready-1', '/tmp/conc-ready-2', '/tmp/conc-start'] as $file) {
            @unlink($file);
        }

        $script = <<<'PHP'
<?php
require '/home/jared/PhpstormProjects/bulk-stream/vendor/autoload.php';
$app = require '/home/jared/PhpstormProjects/bulk-stream/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dispatchId = (int) ($argv[1] ?? 0);
$readyFile = $argv[2] ?? '';
$startFile = $argv[3] ?? '';

touch($readyFile);

$deadline = microtime(true) + 10;
while (! file_exists($startFile) && microtime(true) < $deadline) {
    usleep(10000);
}

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

        file_put_contents('/tmp/process-dispatch-mariadb.php', $script);

        $php = '/home/jared/bin/php';
        $processes = [];

        foreach ([
            [$dispatchOne->id, '/tmp/conc-ready-1'],
            [$dispatchTwo->id, '/tmp/conc-ready-2'],
        ] as [$dispatchId, $readyFile]) {
            $pipes = [];
            $process = proc_open(
                [$php, '/tmp/process-dispatch-mariadb.php', (string) $dispatchId, $readyFile, '/tmp/conc-start'],
                [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ],
                $pipes,
                null,
                [
                    'APP_ENV' => 'testing',
                    'DB_CONNECTION' => 'mysql',
                    'DB_HOST' => 'localhost',
                    'DB_PORT' => '3307',
                    'DB_DATABASE' => 'bulk_stream_concurrency',
                    'DB_USERNAME' => 'bulkstream',
                    'DB_PASSWORD' => 'bulkstream',
                    'DB_SOCKET' => '/home/jared/mariadb-run/mysql.sock',
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

        $deadline = microtime(true) + 10;
        while ((! file_exists('/tmp/conc-ready-1') || ! file_exists('/tmp/conc-ready-2')) && microtime(true) < $deadline) {
            usleep(10000);
        }

        $this->assertFileExists('/tmp/conc-ready-1');
        $this->assertFileExists('/tmp/conc-ready-2');

        touch('/tmp/conc-start');

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

            $losingStderr = implode("\n", array_column(array_filter($processes, fn (array $process): bool => $process['exit_code'] !== 0), 'stderr'));
            $this->assertStringContainsString('InsufficientTankInventory', $losingStderr);

            $this->assertSame('400.000', (string) Tank::find($tank->id)->current_quantity);
            $this->assertSame(1, \App\Models\InventoryMovement::where('tank_id', $tank->id)->count());
            $this->assertSame(1, TruckDispatch::where('status', TruckDispatchStatus::Dispatched)->count());
        } finally {
            \App\Models\InventoryMovement::where('tank_id', $tank->id)->delete();
            TruckDispatch::whereIn('id', [$dispatchOne->id, $dispatchTwo->id])->delete();
            Tank::where('id', $tank->id)->delete();
            Commodity::where('id', $commodity->id)->delete();
            foreach (['/tmp/conc-ready-1', '/tmp/conc-ready-2', '/tmp/conc-start'] as $file) {
                @unlink($file);
            }
            DB::statement('DROP DATABASE IF EXISTS bulk_stream_concurrency');
        }
    }
}
