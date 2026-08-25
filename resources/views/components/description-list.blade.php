@props([
    'divided' => true,
])

<dl {{ $attributes->merge(['class' => $divided ? 'divide-y divide-slate-100 dark:divide-slate-800' : 'space-y-4']) }}>
    {{ $slot }}
</dl>
