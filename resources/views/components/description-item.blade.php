@props([
    'label' => '',
    'value' => null,
])

<div {{ $attributes->merge(['class' => 'py-3.5 sm:grid sm:grid-cols-3 sm:gap-4 items-center']) }}>
    <dt class="text-xs sm:text-sm font-semibold text-slate-500 dark:text-slate-400">
        {{ $label }}
    </dt>
    <dd class="mt-1 sm:col-span-2 sm:mt-0 text-xs sm:text-sm text-slate-900 dark:text-white font-medium">
        {{ $value ?? $slot }}
    </dd>
</div>
