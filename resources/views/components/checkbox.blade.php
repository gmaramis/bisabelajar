@props([
    'disabled' => false,
    'checked' => false,
    'name' => null,
    'value' => '1',
    'label' => null,
    'description' => null,
])

<label class="relative flex items-start gap-3 select-none {{ $disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer' }}">
    <div class="flex h-5 items-center">
        <input type="checkbox"
            {{ $disabled ? 'disabled' : '' }}
            {{ $checked ? 'checked' : '' }}
            @if ($name) name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" @endif
            value="{{ $value }}"
            {!! $attributes->merge([
                'class' => 'h-4.5 w-4.5 rounded-md border-slate-300 text-blue-600 focus:ring-2 focus:ring-blue-500/20 focus:ring-offset-0 dark:border-slate-700 dark:bg-slate-800 dark:checked:bg-blue-600 transition-colors',
            ]) !!}>
    </div>
    @if ($label || $slot->isNotEmpty())
        <div class="text-xs sm:text-sm">
            <span class="font-medium text-slate-900 dark:text-slate-100">{{ $label ?? $slot }}</span>
            @if ($description)
                <p class="text-slate-500 dark:text-slate-400 text-xs mt-0.5">{{ $description }}</p>
            @endif
        </div>
    @endif
</label>
