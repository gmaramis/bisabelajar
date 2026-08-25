@props([
    'type' => 'submit',
    'variant' => 'primary', // primary, secondary, outline, danger, ghost, success
    'size' => 'md',        // sm, md, lg
    'href' => null,
    'disabled' => false,
    'icon' => null,
    'iconPosition' => 'left', // left, right
])

@php
    $baseClasses = 'inline-flex items-center justify-center font-semibold tracking-wide transition-all duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed gap-2 active:scale-[0.98] select-none text-center cursor-pointer';

    $sizeClasses = [
        'sm' => 'min-h-[36px] rounded-lg px-3 py-1.5 text-xs',
        'md' => 'min-h-[44px] sm:min-h-[40px] rounded-lg px-4 py-2 text-xs sm:text-sm uppercase tracking-wider',
        'lg' => 'min-h-[48px] rounded-xl px-5 py-2.5 text-sm sm:text-base',
    ][$size] ?? 'min-h-[44px] sm:min-h-[40px] rounded-lg px-4 py-2 text-xs sm:text-sm';

    $variants = [
        'primary' => 'bg-blue-600 text-white hover:bg-blue-700 focus:bg-blue-700 focus:ring-blue-500 active:bg-blue-800 shadow-xs dark:bg-blue-600 dark:hover:bg-blue-500',
        'secondary' => 'bg-white border border-slate-300 text-slate-700 shadow-xs hover:bg-slate-50 hover:text-slate-900 focus:ring-blue-500 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700 dark:hover:text-white',
        'outline' => 'bg-white border border-blue-600 text-blue-600 shadow-xs hover:bg-blue-600 hover:text-white active:bg-blue-700 focus:ring-blue-500 dark:bg-slate-900 dark:border-blue-500 dark:text-blue-400 dark:hover:bg-blue-600 dark:hover:text-white',
        'danger' => 'bg-rose-600 text-white hover:bg-rose-700 focus:bg-rose-700 focus:ring-rose-500 active:bg-rose-800 shadow-xs dark:bg-rose-600 dark:hover:bg-rose-500',
        'success' => 'bg-emerald-600 text-white hover:bg-emerald-700 focus:bg-emerald-700 focus:ring-emerald-500 active:bg-emerald-800 shadow-xs dark:bg-emerald-600 dark:hover:bg-emerald-500',
        'ghost' => 'bg-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus:ring-slate-400 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100',
    ];

    $classes = "{$baseClasses} {$sizeClasses} " . ($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon && $iconPosition === 'left')
            <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-4 h-4 sm:w-4.5 sm:h-4.5 shrink-0" />
        @endif
        {{ $slot }}
        @if ($icon && $iconPosition === 'right')
            <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-4 h-4 sm:w-4.5 sm:h-4.5 shrink-0" />
        @endif
    </a>
@else
    <button type="{{ $type }}" {{ $disabled ? 'disabled' : '' }} {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon && $iconPosition === 'left')
            <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-4 h-4 sm:w-4.5 sm:h-4.5 shrink-0" />
        @endif
        {{ $slot }}
        @if ($icon && $iconPosition === 'right')
            <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-4 h-4 sm:w-4.5 sm:h-4.5 shrink-0" />
        @endif
    </button>
@endif
