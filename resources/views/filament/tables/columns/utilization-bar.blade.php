@php
    $state = $getState();
@endphp

<div class="flex items-center gap-3">
    <div class="h-2 w-24 overflow-hidden rounded bg-gray-200 dark:bg-white/10">
        <div
            class="h-full rounded bg-primary-500"
            style="width: {{ $state['width'] }}%"
        ></div>
    </div>
    <span class="w-16 text-right text-sm tabular-nums text-gray-950 dark:text-white">
        {{ $state['percent'] }}%
    </span>
</div>
