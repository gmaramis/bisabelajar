@props([
    'disabled' => false,
    'required' => false,
    'name' => null,
    'rows' => 4,
    'hasError' => false,
])

@php
    $hasValidationError = $name && isset($errors) && $errors->has($name);
    $errorState = $hasError || $hasValidationError;

    $borderClasses = $errorState
        ? 'border-rose-300 dark:border-rose-700 focus:border-rose-500 focus:ring-rose-500/20 text-rose-900 dark:text-rose-100'
        : 'border-slate-300 dark:border-slate-700 focus:border-blue-500 focus:ring-blue-500/20 text-slate-900 dark:text-white';
@endphp

<div class="relative w-full">
    <textarea
        rows="{{ $rows }}"
        {{ $disabled ? 'disabled' : '' }}
        @if ($name) name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" @endif
        {!! $attributes->merge([
            'class' => "block w-full appearance-none rounded-lg border bg-white px-3.5 py-2.5 sm:py-2 text-sm placeholder-slate-400 transition-colors duration-150 focus:outline-none focus:ring-2 dark:bg-slate-800 dark:placeholder-slate-500 {$borderClasses} " .
                ($disabled ? 'bg-slate-100 dark:bg-slate-900/60 cursor-not-allowed opacity-75' : ''),
        ]) !!}
        @if ($required) required @endif>{{ $slot }}</textarea>
</div>

@if ($hasValidationError)
    <p class="mt-1.5 text-xs font-medium text-rose-600 dark:text-rose-400 flex items-center gap-1">
        <x-heroicon-s-exclamation-circle class="w-3.5 h-3.5 shrink-0" />
        <span>{{ $errors->first($name) }}</span>
    </p>
@endif
