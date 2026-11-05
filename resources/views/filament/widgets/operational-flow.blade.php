<x-filament-widgets::widget>
<x-filament::section
    heading="Operational flow"
    description="Vessels → Inbound discharges → Storage tanks → Truck dispatches"
>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($steps as $step)
            <a
                href="{{ $step['url'] }}"
                class="flex flex-col gap-1 rounded-md border border-gray-200 bg-white px-4 py-3 transition-colors hover:bg-gray-50 dark:border-white/10 dark:bg-gray-900 dark:hover:bg-white/5"
            >
                <span class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ $step['label'] }}
                </span>
                <span class="text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">
                    {{ $step['count'] }}
                </span>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $step['detail'] }}
                </span>
            </a>
        @endforeach
    </div>
</x-filament::section>
</x-filament-widgets::widget>
