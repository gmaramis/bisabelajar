@props([
    'title' => '',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'space-y-4 sm:space-y-6']) }}>
    @if (isset($breadcrumbs))
        <nav class="flex items-center gap-2 text-xs sm:text-sm text-slate-500 dark:text-slate-400">
            {{ $breadcrumbs }}
        </nav>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200/80 dark:border-slate-800/80 pb-6">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                    {{ $title }}
                </h1>
                @if (isset($badge))
                    {{ $badge }}
                @endif
            </div>
            @if ($description)
                <p class="mt-1.5 text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                    {{ $description }}
                </p>
            @endif
        </div>

        @if (isset($actions))
            <div class="flex flex-wrap items-center gap-2.5">
                {{ $actions }}
            </div>
        @endif
    </div>
</div>
