@props([
    'empty' => false,
    'emptyMessage' => 'Tidak ada data yang ditemukan.',
    'emptyIcon' => 'inbox',
    'pagination' => null,
])

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl sm:rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900']) }}>
    <div class="overflow-x-auto custom-scrollbar">
        <table class="w-full text-left text-xs sm:text-sm text-slate-900 dark:text-slate-100 min-w-[540px]">
            @if (isset($header))
                <thead class="border-b border-slate-100 bg-slate-50/75 text-[10px] sm:text-xs font-bold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                    {{ $header }}
                </thead>
            @endif

            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @if ($empty)
                    <tr>
                        <td colspan="100%" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
                                    <x-dynamic-component :component="'heroicon-o-' . $emptyIcon" class="w-6 h-6" />
                                </div>
                                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $emptyMessage }}</p>
                                {{ $emptySlot ?? '' }}
                            </div>
                        </td>
                    </tr>
                @else
                    {{ $slot }}
                @endif
            </tbody>
        </table>
    </div>

    @if ($pagination || isset($paginationSlot))
        <div class="border-t border-slate-100 px-4 py-3 sm:px-6 sm:py-4 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/60">
            {{ $pagination ?? $paginationSlot }}
        </div>
    @endif
</div>
