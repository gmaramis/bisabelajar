@props([
    'variant' => 'default', // primary, success, warning, danger, info, gray, neutral
    'size' => 'md',        // sm, md, lg
    'dot' => false,
    'icon' => null,
])

@php
    $variantMap = [
        'primary' => 'bg-blue-50 text-blue-700 border-blue-200/60 dark:bg-blue-950/60 dark:text-blue-400 dark:border-blue-800/60',
        'success' => 'bg-emerald-50 text-emerald-700 border-emerald-200/60 dark:bg-emerald-950/60 dark:text-emerald-400 dark:border-emerald-800/60',
        'warning' => 'bg-amber-50 text-amber-700 border-amber-200/60 dark:bg-amber-950/60 dark:text-amber-400 dark:border-amber-800/60',
        'danger'  => 'bg-rose-50 text-rose-700 border-rose-200/60 dark:bg-rose-950/60 dark:text-rose-400 dark:border-rose-800/60',
        'info'    => 'bg-blue-50 text-blue-700 border-blue-200/60 dark:bg-blue-950/60 dark:text-blue-400 dark:border-blue-800/60',
        'gray'    => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
        'neutral' => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
        'default' => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
    ];

    $dotMap = [
        'primary' => 'bg-blue-500',
        'success' => 'bg-emerald-500',
        'warning' => 'bg-amber-500',
        'danger'  => 'bg-rose-500',
        'info'    => 'bg-blue-500',
        'gray'    => 'bg-slate-400',
        'neutral' => 'bg-slate-400',
        'default' => 'bg-slate-400',
    ];

    $sizeMap = [
        'sm' => 'px-2 py-0.5 text-[10px]',
        'md' => 'px-2.5 py-1 text-xs',
        'lg' => 'px-3 py-1.5 text-sm font-semibold',
    ];

    $classes = $variantMap[strtolower($variant)] ?? $variantMap['default'];
    $sizeClasses = $sizeMap[$size] ?? $sizeMap['md'];
    $dotColor = $dotMap[strtolower($variant)] ?? $dotMap['default'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 font-medium rounded-full border tracking-wide {$classes} {$sizeClasses}"]) }}>
    @if ($dot)
        <span class="h-1.5 w-1.5 rounded-full {{ $dotColor }} shrink-0"></span>
    @endif
    @if ($icon)
        <x-dynamic-component :component="'heroicon-s-' . $icon" class="w-3.5 h-3.5 shrink-0" />
    @endif
    {{ $slot }}
</span>
