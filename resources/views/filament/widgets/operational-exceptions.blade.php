<x-filament-widgets::widget>
<x-filament::section
    heading="Operational attention"
    description="Pending work and records that need an operator decision."
>
    @if (empty($groups))
        <x-filament::empty-state
            icon="heroicon-o-check-circle"
            icon-color="success"
            heading="No operational exceptions"
            description="All current records are within normal operating states."
            compact
        />
    @else
        <div class="space-y-6">
            @foreach ($groups as $group)
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $group['title'] }}
                        </h3>
                        <x-filament::badge :color="$group['severity']">
                            {{ $group['count'] }}
                        </x-filament::badge>
                    </div>

                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $group['description'] }}
                    </p>

                    <ul class="mt-2 divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($group['records'] as $record)
                            <li>
                                <a
                                    href="{{ $record['url'] }}"
                                    class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-2 text-sm hover:text-primary-600 dark:hover:text-primary-400"
                                >
                                    <span class="font-medium text-gray-950 dark:text-white">
                                        {{ $record['title'] }}
                                    </span>
                                    <span class="ms-auto text-gray-500 dark:text-gray-400">
                                        {{ $record['description'] }}
                                    </span>
                                    <x-filament::icon
                                        icon="heroicon-m-arrow-right"
                                        class="h-4 w-4 shrink-0 text-gray-400"
                                    />
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    @if ($group['count'] > count($group['records']) && $group['url'] !== '#')
                        <div class="mt-2">
                            <x-filament::link :href="$group['url']" size="sm">
                                View all {{ $group['count'] }}
                            </x-filament::link>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-filament::section>
</x-filament-widgets::widget>
