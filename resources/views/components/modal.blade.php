@props([
    'name' => 'modal-' . uniqid(),
    'title' => null,
    'maxWidth' => '2xl', // sm, md, lg, xl, 2xl, 4xl
])

@php
    $maxWidthClass = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        '4xl' => 'sm:max-w-4xl',
    ][$maxWidth] ?? 'sm:max-w-2xl';
@endphp

<div x-data="{ open: false }"
     x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true"
     x-on:close-modal.window="if ($event.detail === '{{ $name }}') open = false"
     x-on:keydown.escape.window="open = false"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto px-3 py-4 sm:px-0 flex items-end sm:items-center justify-center">

    <div x-show="open"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="open = false"
         class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity"></div>

    <div x-show="open"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
         class="relative w-full overflow-hidden rounded-t-2xl sm:rounded-2xl bg-white border border-slate-100 shadow-2xl transition-all dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100 max-h-[90vh] flex flex-col {{ $maxWidthClass }}">

        @if ($title || isset($header))
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3.5 sm:px-6 sm:py-4 dark:border-slate-800">
                <h3 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white">{{ $title ?? $header }}</h3>
                <button @click="open = false" type="button" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors dark:hover:bg-slate-800 dark:hover:text-slate-200" aria-label="Close modal">
                    <x-heroicon-o-x-mark class="w-5 h-5" />
                </button>
            </div>
        @endif

        <div class="p-4 sm:p-6 overflow-y-auto">
            {{ $slot }}
        </div>

        @if (isset($footer) || isset($footerSlot))
            <div class="flex items-center justify-end gap-2 sm:gap-3 border-t border-slate-100 bg-slate-50/50 px-4 py-3 sm:px-6 sm:py-4 dark:border-slate-800 dark:bg-slate-900/60">
                {{ $footer ?? $footerSlot }}
            </div>
        @endif
    </div>
</div>
