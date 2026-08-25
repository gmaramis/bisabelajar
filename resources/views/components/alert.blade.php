@props([
    'variant' => 'info', // info, success, warning, danger
    'title' => null,
    'dismissible' => false,
])

@php
    $variantMap = [
        'info' => [
            'box' => 'bg-blue-50/80 border-blue-200 text-blue-900 dark:bg-blue-950/40 dark:border-blue-800 dark:text-blue-200',
            'icon' => 'heroicon-s-information-circle',
            'iconColor' => 'text-blue-500 dark:text-blue-400',
        ],
        'success' => [
            'box' => 'bg-emerald-50/80 border-emerald-200 text-emerald-900 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-200',
            'icon' => 'heroicon-s-check-circle',
            'iconColor' => 'text-emerald-500 dark:text-emerald-400',
        ],
        'warning' => [
            'box' => 'bg-amber-50/80 border-amber-200 text-amber-900 dark:bg-amber-950/40 dark:border-amber-800 dark:text-amber-200',
            'icon' => 'heroicon-s-exclamation-triangle',
            'iconColor' => 'text-amber-500 dark:text-amber-400',
        ],
        'danger' => [
            'box' => 'bg-rose-50/80 border-rose-200 text-rose-900 dark:bg-rose-950/40 dark:border-rose-800 dark:text-rose-200',
            'icon' => 'heroicon-s-x-circle',
            'iconColor' => 'text-rose-500 dark:text-rose-400',
        ],
    ];

    $cfg = $variantMap[$variant] ?? $variantMap['info'];
@endphp

<div x-data="{ dismissed: false }"
     x-show="!dismissed"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     {{ $attributes->merge(['class' => "flex items-start gap-3 rounded-xl border p-3.5 sm:p-4 text-xs sm:text-sm {$cfg['box']}"]) }}
     role="alert">
    <div class="shrink-0 pt-0.5">
        <x-dynamic-component :component="$cfg['icon']" class="w-5 h-5 {{ $cfg['iconColor'] }}" />
    </div>

    <div class="flex-1">
        @if ($title)
            <h5 class="font-bold mb-0.5 leading-snug">{{ $title }}</h5>
        @endif
        <div class="leading-relaxed">
            {{ $slot }}
        </div>
    </div>

    @if ($dismissible)
        <button @click="dismissed = true" type="button" class="shrink-0 rounded-md p-1 opacity-70 hover:opacity-100 transition-opacity" aria-label="Dismiss alert">
            <x-heroicon-o-x-mark class="w-4 h-4" />
        </button>
    @endif
</div>
