@props([
    'paginator' => null,
    'currentPage' => 1,
    'totalPages' => 1,
    'totalItems' => null,
    'perPage' => 15,
    'pageName' => 'page',
    'onEachSide' => 2,
    'showJump' => true,
    'showSummary' => true,
])

@php
    if ($paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
        $currentPage = $paginator->currentPage();
        $totalPages = $paginator->lastPage();
        $totalItems = $paginator->total();
        $perPage = $paginator->perPage();
        $pageName = $paginator->getPageName();
    } elseif ($paginator instanceof \Illuminate\Contracts\Pagination\Paginator) {
        $currentPage = $paginator->currentPage();
        $totalPages = $paginator->hasMorePages() ? $currentPage + 1 : $currentPage;
        $perPage = $paginator->perPage();
        $pageName = $paginator->getPageName();
    }

    $currentPage = max(1, (int) $currentPage);
    $totalPages = max(1, (int) $totalPages);

    $getUrl = function ($page) use ($paginator, $pageName) {
        if ($paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator || $paginator instanceof \Illuminate\Contracts\Pagination\Paginator) {
            return $paginator->url($page);
        }
        return request()->fullUrlWithQuery([$pageName => $page]);
    };

    $startPage = max(1, $currentPage - $onEachSide);
    $endPage = min($totalPages, $currentPage + $onEachSide);

    if ($currentPage <= $onEachSide + 1) {
        $endPage = min($totalPages, (1 + ($onEachSide * 2)));
    }

    if ($currentPage >= $totalPages - $onEachSide) {
        $startPage = max(1, ($totalPages - ($onEachSide * 2)));
    }

    $hasPrevious = $currentPage > 1;
    $hasNext = $currentPage < $totalPages;

    $firstItem = $totalItems !== null ? (($currentPage - 1) * $perPage) + 1 : null;
    $lastItem = $totalItems !== null ? min($currentPage * $perPage, $totalItems) : null;
@endphp

@if ($totalPages > 1 || $totalItems !== null)
    <div {{ $attributes->merge(['class' => 'flex flex-col lg:flex-row items-center justify-between gap-4 py-4 w-full text-slate-700 dark:text-slate-200 select-none']) }}>
        @if ($showSummary && $totalItems !== null)
            <div class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 text-center lg:text-left">
                Menampilkan <span class="font-bold text-slate-900 dark:text-white">{{ number_format($firstItem) }}</span> sampai <span class="font-bold text-slate-900 dark:text-white">{{ number_format($lastItem) }}</span> dari <span class="font-bold text-slate-900 dark:text-white">{{ number_format($totalItems) }}</span> data
            </div>
        @else
            <div></div>
        @endif

        <div class="flex flex-wrap items-center justify-center gap-2 sm:gap-3">
            <nav aria-label="Navigasi Halaman" class="inline-flex items-center gap-1 rounded-xl border border-slate-200/80 bg-white p-1 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                @if ($hasPrevious)
                    <a 
                        href="{{ $getUrl(1) }}" 
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white transition-colors"
                        title="Halaman Pertama"
                        aria-label="First page"
                    >
                        <x-heroicon-m-chevron-double-left class="w-4 h-4" />
                    </a>
                    <a 
                        href="{{ $getUrl($currentPage - 1) }}" 
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white transition-colors"
                        title="Halaman Sebelumnya"
                        aria-label="Previous page"
                        rel="prev"
                    >
                        <x-heroicon-m-chevron-left class="w-4 h-4" />
                    </a>
                @else
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-300 dark:text-slate-600 cursor-not-allowed">
                        <x-heroicon-m-chevron-double-left class="w-4 h-4" />
                    </span>
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-300 dark:text-slate-600 cursor-not-allowed">
                        <x-heroicon-m-chevron-left class="w-4 h-4" />
                    </span>
                @endif

                @if ($startPage > 1)
                    <a 
                        href="{{ $getUrl(1) }}" 
                        class="inline-flex h-8 min-w-[32px] px-2.5 items-center justify-center rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white transition-colors"
                    >
                        1
                    </a>
                    @if ($startPage > 2)
                        <span class="inline-flex h-8 min-w-[28px] items-center justify-center text-xs font-bold text-slate-400 dark:text-slate-500">
                            &hellip;
                        </span>
                    @endif
                @endif

                @for ($page = $startPage; $page <= $endPage; $page++)
                    @if ($page === $currentPage)
                        <span 
                            aria-current="page"
                            class="inline-flex h-8 min-w-[32px] px-2.5 items-center justify-center rounded-lg bg-blue-600 text-xs font-bold text-white shadow-2xs"
                        >
                            {{ $page }}
                        </span>
                    @else
                        <a 
                            href="{{ $getUrl($page) }}" 
                            class="inline-flex h-8 min-w-[32px] px-2.5 items-center justify-center rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white transition-colors"
                        >
                            {{ $page }}
                        </a>
                    @endif
                @endfor

                @if ($endPage < $totalPages)
                    @if ($endPage < $totalPages - 1)
                        <span class="inline-flex h-8 min-w-[28px] items-center justify-center text-xs font-bold text-slate-400 dark:text-slate-500">
                            &hellip;
                        </span>
                    @endif
                    <a 
                        href="{{ $getUrl($totalPages) }}" 
                        class="inline-flex h-8 min-w-[32px] px-2.5 items-center justify-center rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white transition-colors"
                    >
                        {{ number_format($totalPages) }}
                    </a>
                @endif

                @if ($hasNext)
                    <a 
                        href="{{ $getUrl($currentPage + 1) }}" 
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white transition-colors"
                        title="Halaman Selanjutnya"
                        aria-label="Next page"
                        rel="next"
                    >
                        <x-heroicon-m-chevron-right class="w-4 h-4" />
                    </a>
                    <a 
                        href="{{ $getUrl($totalPages) }}" 
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white transition-colors"
                        title="Halaman Terakhir"
                        aria-label="Last page"
                    >
                        <x-heroicon-m-chevron-double-right class="w-4 h-4" />
                    </a>
                @else
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-300 dark:text-slate-600 cursor-not-allowed">
                        <x-heroicon-m-chevron-right class="w-4 h-4" />
                    </span>
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-300 dark:text-slate-600 cursor-not-allowed">
                        <x-heroicon-m-chevron-double-right class="w-4 h-4" />
                    </span>
                @endif
            </nav>

            @if ($showJump && $totalPages > 5)
                <form 
                    method="GET" 
                    action="{{ request()->url() }}" 
                    class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400"
                    x-data="{ targetPage: '{{ $currentPage }}' }"
                    @submit.prevent="
                        let p = parseInt(targetPage);
                        if (p >= 1 && p <= {{ $totalPages }}) {
                            let url = new URL(window.location.href);
                            url.searchParams.set('{{ $pageName }}', p);
                            window.location.href = url.toString();
                        }
                    "
                >
                    @foreach (request()->except([$pageName]) as $key => $val)
                        @if (is_array($val))
                            @foreach ($val as $subVal)
                                <input type="hidden" name="{{ $key }}[]" value="{{ $subVal }}">
                            @endforeach
                        @else
                            <input type="hidden" name="{{ $key }}" value="{{ $val }}">
                        @endif
                    @endforeach

                    <span>Halaman</span>
                    <input 
                        type="number" 
                        min="1" 
                        max="{{ $totalPages }}" 
                        x-model="targetPage"
                        class="h-8 w-14 rounded-lg border border-slate-200 bg-white px-1.5 text-center text-xs font-bold text-slate-900 shadow-2xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-800 dark:bg-slate-900 dark:text-white"
                        aria-label="Nomor halaman langsung"
                    />
                    <span>dari {{ number_format($totalPages) }}</span>
                    <button 
                        type="submit" 
                        class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200/80 bg-slate-50 px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white transition-colors"
                    >
                        Lompat
                    </button>
                </form>
            @endif
        </div>
    </div>
@endif
