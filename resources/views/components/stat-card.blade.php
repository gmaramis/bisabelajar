@props([
    'title' => null,
    'value' => null,
    'icon' => null,
    'tag' => null,
    'trend' => null,
    'trendUp' => true,
    'color' => 'blue', // blue, emerald, amber, rose, indigo
])

@php
    $colorMap = [
        'blue' => 'bg-blue-50 text-blue-600 dark:bg-blue-950/60 dark:text-blue-400',
        'emerald' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400',
        'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400',
        'rose' => 'bg-rose-50 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400',
        'indigo' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400',
    ];

    $iconClasses = $colorMap[$color] ?? $colorMap['blue'];
@endphp

<div {{ $attributes->merge(['class' => 'group rounded-xl sm:rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs transition-all duration-200 hover:shadow-md dark:border-slate-800 dark:bg-slate-900']) }}>
    <div class="mb-3 sm:mb-4 flex items-center justify-between">
        @if ($icon)
            <div class="flex h-10 w-10 sm:h-12 sm:w-12 items-center justify-center rounded-xl {{ $iconClasses }} transition-transform duration-200 group-hover:scale-105">
                <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-5 h-5 sm:w-6 sm:h-6" />
            </div>
        @else
            <div></div>
        @endif

        @if ($tag)
            <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">{{ $tag }}</span>
        @endif
    </div>

    <div>
        <div class="flex items-baseline justify-between gap-2">
            <span class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                {{ $value ?? $slot }}
            </span>

            @if ($trend)
                <span class="inline-flex items-center text-xs font-semibold {{ $trendUp ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                    <x-dynamic-component :component="$trendUp ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down'" class="w-3.5 h-3.5 mr-0.5" />
                    {{ $trend }}
                </span>
            @endif
        </div>

        @if ($title)
            <p class="mt-1 text-xs sm:text-sm font-medium text-slate-500 dark:text-slate-400">{{ $title }}</p>
        @endif
    </div>
</div>
