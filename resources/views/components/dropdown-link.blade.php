@props([
    'icon' => null,
    'danger' => false,
])

@php
    $colorClasses = $danger
        ? 'text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 focus:bg-rose-50 dark:focus:bg-rose-950/40'
        : 'text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/60 focus:bg-slate-50 dark:focus:bg-slate-800/60';
@endphp

<a {{ $attributes->merge(['class' => "flex items-center gap-2.5 w-full px-4 py-2 text-start text-xs sm:text-sm font-medium transition duration-150 ease-in-out focus:outline-none {$colorClasses}"]) }}>
    @if ($icon)
        <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-4.5 h-4.5 shrink-0 opacity-70" />
    @endif
    <span>{{ $slot }}</span>
</a>
