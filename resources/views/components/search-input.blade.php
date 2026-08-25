@props([
    'placeholder' => 'Cari...',
    'name' => 'search',
    'value' => '',
])

<div x-data="{ query: '{{ $value }}' }" class="relative w-full">
    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center ps-3 text-slate-400 dark:text-slate-500">
        <x-heroicon-o-magnifying-glass class="w-4.5 h-4.5" />
    </div>

    <input type="search"
        name="{{ $name }}"
        x-model="query"
        placeholder="{{ $placeholder }}"
        {!! $attributes->merge([
            'class' => 'block w-full appearance-none rounded-lg border border-slate-300 bg-white py-2.5 sm:py-2 ps-10 pe-9 text-sm text-slate-900 placeholder-slate-400 transition-colors duration-150 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500',
        ]) !!}>

    <button type="button"
        x-show="query.length > 0"
        @click="query = ''; $el.previousElementSibling.focus()"
        class="absolute inset-y-0 right-0 flex items-center pe-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors"
        aria-label="Clear search">
        <x-heroicon-s-x-mark class="w-4 h-4" />
    </button>
</div>
