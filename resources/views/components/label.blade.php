@props([
    'value' => null,
    'required' => false,
])

<label {{ $attributes->merge(['class' => 'block text-xs sm:text-sm font-semibold text-slate-700 dark:text-slate-200 tracking-wide mb-1.5']) }}>
    {{ $value ?? $slot }}
    @if ($required)
        <span class="text-rose-500 font-bold ms-0.5" title="Wajib diisi">*</span>
    @endif
</label>
