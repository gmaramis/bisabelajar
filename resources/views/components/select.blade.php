@props([
    'disabled' => false,
    'required' => false,
    'name' => null,
    'icon' => null,
    'hasError' => false,
])

@php
    $hasValidationError = $name && isset($errors) && $errors->has($name);
    $errorState = $hasError || $hasValidationError;

    $borderClasses = $errorState
        ? 'border-rose-300 dark:border-rose-700 focus:border-rose-500 focus:ring-rose-500/20 text-rose-900 dark:text-rose-100'
        : 'border-slate-300 dark:border-slate-700 focus:border-blue-500 focus:ring-blue-500/20 text-slate-900 dark:text-white';
        
    $paddingClasses = $icon ? 'ps-10 pe-9' : 'ps-3.5 pe-9';
@endphp

<div class="relative w-full">
    @if ($icon)
        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center ps-3 text-slate-400 dark:text-slate-500">
            <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-4.5 h-4.5" />
        </div>
    @endif

    <select
        {{ $disabled ? 'disabled' : '' }}
        @if ($name) name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" @endif
        {!! $attributes->merge([
            'class' => "block w-full appearance-none rounded-lg border bg-white py-2.5 sm:py-2 text-sm transition-colors duration-150 focus:outline-none focus:ring-2 dark:bg-slate-800 {$borderClasses} {$paddingClasses} " .
                ($disabled ? 'bg-slate-100 dark:bg-slate-900/60 cursor-not-allowed opacity-75' : ''),
        ]) !!}
        @if ($required) required @endif>
        {{ $slot }}
    </select>

    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pe-3 text-slate-400 dark:text-slate-500">
        <x-heroicon-m-chevron-down class="w-4.5 h-4.5" />
    </div>
</div>

@if ($hasValidationError)
    <p class="mt-1.5 text-xs font-medium text-rose-600 dark:text-rose-400 flex items-center gap-1">
        <x-heroicon-s-exclamation-circle class="w-3.5 h-3.5 shrink-0" />
        <span>{{ $errors->first($name) }}</span>
    </p>
@endif
