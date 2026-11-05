<x-filament::page>
    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3">
        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-950 dark:text-white">Period</span>
            <select name="period" class="fi-input fi-input-has-inline-prefix rounded-md border-gray-300 text-sm">
                <option value="today" @selected($period === 'today')>Today</option>
                <option value="last_7_days" @selected($period === 'last_7_days')>Last 7 days</option>
                <option value="current_month" @selected($period === 'current_month')>Current month</option>
                <option value="previous_month" @selected($period === 'previous_month')>Previous month</option>
                <option value="custom" @selected($period === 'custom')>Custom range</option>
            </select>
        </label>

        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-950 dark:text-white">Start date</span>
            <input type="date" name="start_date" value="{{ $startDate }}" class="fi-input rounded-md border-gray-300 text-sm">
        </label>

        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-950 dark:text-white">End date</span>
            <input type="date" name="end_date" value="{{ $endDate }}" class="fi-input rounded-md border-gray-300 text-sm">
        </label>

        <button type="submit" class="fi-btn fi-size-md fi-color fi-color-primary rounded-md px-3 py-2 text-sm font-medium">
            Apply
        </button>
    </form>

    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">
        Reporting period: {{ $periodLabel }} (Africa/Nairobi). Database timestamps are stored in UTC.
    </p>

    <x-filament::section heading="Period summary" class="mb-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-md border border-gray-200 p-4 dark:border-white/10">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Received net (MT)</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums">{{ \App\Filament\Support\Quantity::mass($summary['received']['net_received_mass']) }}</div>
                <div class="mt-1 text-xs text-gray-500">{{ $summary['received']['operations'] }} completed discharge(s)</div>
            </div>
            <div class="rounded-md border border-gray-200 p-4 dark:border-white/10">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Gross received (MT)</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums">{{ \App\Filament\Support\Quantity::mass($summary['received']['gross_mass']) }}</div>
                <div class="mt-1 text-xs text-gray-500">Transit loss {{ \App\Filament\Support\Quantity::mass($summary['received']['transit_loss_mass']) }} MT</div>
            </div>
            <div class="rounded-md border border-gray-200 p-4 dark:border-white/10">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Dispatched (MT)</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums">{{ \App\Filament\Support\Quantity::mass($summary['dispatched']['quantity']) }}</div>
                <div class="mt-1 text-xs text-gray-500">{{ $summary['dispatched']['operations'] }} completed dispatch(es)</div>
            </div>
            <div class="rounded-md border border-gray-200 p-4 dark:border-white/10">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Current pending work (all periods)</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums">{{ $summary['pending']['draft_discharges'] + $summary['pending']['processing_discharges'] + $summary['pending']['planned_dispatches'] + $summary['pending']['loading_dispatches'] + $summary['pending']['arriving_vessels'] }}</div>
                <div class="mt-1 text-xs text-gray-500">Outstanding records; not limited to the selected period.</div>
            </div>
        </div>
    </x-filament::section>

    <div class="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="flex flex-col gap-4">
            @livewire(\App\Filament\Widgets\ReceivedMassChart::class, [
                'period' => $period,
                'startDate' => $startDate,
                'endDate' => $endDate,
            ])

            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Transit loss for this period</div>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ \App\Filament\Support\Quantity::mass($summary['received']['transit_loss_mass']) }}</span>
                    <span class="text-xs font-medium text-gray-500">MT</span>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    0.5% of {{ \App\Filament\Support\Quantity::mass($summary['received']['gross_mass']) }} MT gross received. Reported as a figure rather than a third bar series so the gross and net bars remain readable.
                </p>
            </div>
        </div>

        <div>
            @livewire(\App\Filament\Widgets\DispatchVolumeChart::class, [
                'period' => $period,
                'startDate' => $startDate,
                'endDate' => $endDate,
            ])
        </div>
    </div>

    <div class="mb-6">
        @livewire(\App\Filament\Widgets\TankUtilizationChart::class)
    </div>

    <div class="mb-6">
        @livewire(\App\Filament\Widgets\CommodityThroughputChart::class, [
            'period' => $period,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ])
    </div>

    <x-filament::section heading="Commodity breakdown" class="mb-6">
        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-sm">
                <thead>
                    <tr>
                        <th class="fi-ta-header-cell px-3 py-2 text-left">Commodity</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-right">Received gross (MT)</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-right">Transit loss (MT)</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-right">Received net (MT)</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-right">Dispatched (MT)</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-right">Current inventory (MT)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($commodities as $row)
                        <tr class="fi-ta-row">
                            <td class="fi-ta-cell px-3 py-2">{{ $row['code'] }} — {{ $row['name'] }}</td>
                            <td class="fi-ta-cell px-3 py-2 text-right tabular-nums">{{ \App\Filament\Support\Quantity::mass($row['received_gross_mass']) }}</td>
                            <td class="fi-ta-cell px-3 py-2 text-right tabular-nums">{{ \App\Filament\Support\Quantity::mass($row['transit_loss_mass']) }}</td>
                            <td class="fi-ta-cell px-3 py-2 text-right tabular-nums">{{ \App\Filament\Support\Quantity::mass($row['received_net_mass']) }}</td>
                            <td class="fi-ta-cell px-3 py-2 text-right tabular-nums">{{ \App\Filament\Support\Quantity::mass($row['dispatched_quantity']) }}</td>
                            <td class="fi-ta-cell px-3 py-2 text-right tabular-nums">{{ \App\Filament\Support\Quantity::mass($row['current_inventory']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-4 text-center text-gray-500">No commodity data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section heading="Tank inventory" class="mb-6">
        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-sm">
                <thead>
                    <tr>
                        <th class="fi-ta-header-cell px-3 py-2 text-left">Tank</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-left">Commodity</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-right">Capacity (MT)</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-right">Current (MT)</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-right">Available (MT)</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-right">Utilization</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-left">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tanks as $tank)
                        <tr class="fi-ta-row">
                            <td class="fi-ta-cell px-3 py-2">{{ $tank['code'] }}</td>
                            <td class="fi-ta-cell px-3 py-2">{{ $tank['commodity'] }}</td>
                            <td class="fi-ta-cell px-3 py-2 text-right tabular-nums">{{ \App\Filament\Support\Quantity::mass($tank['capacity']) }}</td>
                            <td class="fi-ta-cell px-3 py-2 text-right tabular-nums">{{ \App\Filament\Support\Quantity::mass($tank['current_quantity']) }}</td>
                            <td class="fi-ta-cell px-3 py-2 text-right tabular-nums">{{ \App\Filament\Support\Quantity::mass($tank['available_capacity']) }}</td>
                            <td class="fi-ta-cell px-3 py-2 text-right tabular-nums">{{ $tank['utilization'] }}%</td>
                            <td class="fi-ta-cell px-3 py-2">{{ $tank['status'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-4 text-center text-gray-500">No tanks configured.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section heading="Discharge and transit-loss analysis" class="mb-6">
        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-sm">
                <thead>
                    <tr>
                        <th class="fi-ta-header-cell px-3 py-2 text-left">Reference</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-left">Vessel</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-left">Tank</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-left">Commodity</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-right">Gross (MT)</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-right">Loss (MT)</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-right">Net (MT)</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-right">Loss %</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-right">Logs</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-left">Completed</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-left">Resulting movement</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($discharges as $discharge)
                        <tr class="fi-ta-row">
                            <td class="fi-ta-cell px-3 py-2">{{ $discharge['reference'] }}</td>
                            <td class="fi-ta-cell px-3 py-2">{{ $discharge['vessel'] }}</td>
                            <td class="fi-ta-cell px-3 py-2">{{ $discharge['tank'] }}</td>
                            <td class="fi-ta-cell px-3 py-2">{{ $discharge['commodity'] }}</td>
                            <td class="fi-ta-cell px-3 py-2 text-right tabular-nums">{{ \App\Filament\Support\Quantity::mass($discharge['gross_mass']) }}</td>
                            <td class="fi-ta-cell px-3 py-2 text-right tabular-nums">{{ \App\Filament\Support\Quantity::mass($discharge['transit_loss_mass']) }}</td>
                            <td class="fi-ta-cell px-3 py-2 text-right tabular-nums">{{ \App\Filament\Support\Quantity::mass($discharge['net_received_mass']) }}</td>
                            <td class="fi-ta-cell px-3 py-2 text-right tabular-nums">{{ $discharge['loss_percentage'] }}%</td>
                            <td class="fi-ta-cell px-3 py-2 text-right tabular-nums">{{ $discharge['source_log_count'] }}</td>
                            <td class="fi-ta-cell px-3 py-2">{{ $discharge['completed_at'] ? $discharge['completed_at']->format('Y-m-d H:i') : '—' }}</td>
                            <td class="fi-ta-cell px-3 py-2">{{ $discharge['resulting_movement'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="px-3 py-4 text-center text-gray-500">No completed discharges in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section heading="Inventory reconciliation" class="mb-6">
        <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">
            Read-only comparison of stored tank balances and balances reconstructed from inventory movements. A zero difference indicates reconciliation. Non-zero differences are reported for investigation only.
        </p>
        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-sm">
                <thead>
                    <tr>
                        <th class="fi-ta-header-cell px-3 py-2 text-left">Tank</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-left">Commodity</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-right">Stored (MT)</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-right">Reconstructed (MT)</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-right">Difference (MT)</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-right">Movements</th>
                        <th class="fi-ta-header-cell px-3 py-2 text-left">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reconciliation as $row)
                        <tr class="fi-ta-row">
                            <td class="fi-ta-cell px-3 py-2">{{ $row['code'] }}</td>
                            <td class="fi-ta-cell px-3 py-2">{{ $row['commodity'] }}</td>
                            <td class="fi-ta-cell px-3 py-2 text-right tabular-nums">{{ \App\Filament\Support\Quantity::mass($row['stored_balance']) }}</td>
                            <td class="fi-ta-cell px-3 py-2 text-right tabular-nums">{{ \App\Filament\Support\Quantity::mass($row['reconstructed_balance']) }}</td>
                            <td class="fi-ta-cell px-3 py-2 text-right tabular-nums">{{ \App\Filament\Support\Quantity::mass($row['difference']) }}</td>
                            <td class="fi-ta-cell px-3 py-2 text-right tabular-nums">{{ $row['movement_count'] }}</td>
                            <td class="fi-ta-cell px-3 py-2">{{ $row['status'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-4 text-center text-gray-500">No tanks configured.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament::page>
