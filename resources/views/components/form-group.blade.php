@props([
    'label' => null,
    'name' => null,
    'required' => false,
    'help' => null,
])

<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    @if ($label)
        <x-label :value="$label" :for="$name" :required="$required" />
    @endif

    {{ $slot }}

    @if ($help)
        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $help }}</p>
    @endif
</div>
