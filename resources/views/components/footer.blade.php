@props([
    'appName' => config('app.name', 'BisaBelajar'),
])

<footer {{ $attributes->merge(['class' => 'bg-white dark:bg-slate-950 border-t border-slate-200/80 dark:border-slate-800 py-8 sm:py-12 transition-colors overflow-hidden']) }}>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500 dark:text-slate-400">
            <div class="font-semibold text-slate-700 dark:text-slate-200">
                <span>{{ $appName }} — AI-VET Learning Platform</span>
            </div>
            <p>© {{ date('Y') }} {{ $appName }} Pilot. Hak Cipta Dilindungi Undang-Undang.</p>
        </div>
    </div>
</footer>
