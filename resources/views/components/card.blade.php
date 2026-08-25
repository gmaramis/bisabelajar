@props([
    'title' => null,
    'subtitle' => null,
    'variant' => 'default', // default, flat, accent
    'actions' => null,
    'footer' => null,
    'headerClass' => '',
    'bodyClass' => '',
    'footerClass' => '',
])

@php
    $variants = [
        'default' => 'bg-white border border-slate-200/80 shadow-xs rounded-xl sm:rounded-2xl dark:bg-slate-900 dark:border-slate-800 dark:text-slate-100',
        'flat' => 'bg-white border border-slate-200 rounded-xl sm:rounded-2xl dark:bg-slate-900 dark:border-slate-800 dark:text-slate-100',
        'accent' => 'bg-gradient-to-br from-blue-900 via-blue-800 to-blue-700 text-white rounded-xl sm:rounded-2xl shadow-md border border-blue-600/30',
    ];
    $cardClasses = $variants[$variant] ?? $variants['default'];
@endphp

<div {{ $attributes->merge(['class' => "overflow-hidden transition-all duration-200 {$cardClasses}"]) }}>
    @if ($title || isset($header) || $actions || isset($actionsSlot))
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 px-4 py-3 sm:px-6 sm:py-4.5 dark:border-slate-800/80 {{ $headerClass }}">
            @if (isset($header))
                {{ $header }}
            @else
                <div>
                    @if ($title)
                        <h3 class="text-sm sm:text-base font-bold leading-snug text-slate-900 dark:text-white {{ $variant === 'accent' ? '!text-white' : '' }}">{{ $title }}</h3>
                    @endif
                    @if ($subtitle)
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400 {{ $variant === 'accent' ? '!text-blue-100/80' : '' }}">{{ $subtitle }}</p>
                    @endif
                </div>
            @endif

            @if ($actions || isset($actionsSlot))
                <div class="flex items-center gap-2 self-end sm:self-auto shrink-0">
                    {{ $actions ?? $actionsSlot }}
                </div>
            @endif
        </div>
    @endif

    <div class="p-4 sm:p-6 {{ $bodyClass }}">
        {{ $slot }}
    </div>

    @if ($footer || isset($footerSlot))
        <div class="border-t border-slate-100 bg-slate-50/50 px-4 py-3 sm:px-6 sm:py-4 dark:border-slate-800/80 dark:bg-slate-900/60 {{ $footerClass }}">
            {{ $footer ?? $footerSlot }}
        </div>
    @endif
</div>
