@props([
    'href' => url('/'),
    'label' => null,
    'bordered' => true,
])

<div {{ $attributes->merge(['class' => $bordered ? 'mt-6 pt-4 border-t border-slate-100 dark:border-slate-800 text-center text-xs text-slate-500 dark:text-slate-400' : 'text-xs text-slate-500 dark:text-slate-400']) }}>
    <a href="{{ $href }}" class="inline-flex items-center gap-1.5 font-medium text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400 transition-colors">
        <x-heroicon-m-arrow-left class="w-3.5 h-3.5 shrink-0" />
        <span>{{ $label ?? ($slot->isNotEmpty() ? $slot : 'Kembali ke Beranda') }}</span>
    </a>
</div>
