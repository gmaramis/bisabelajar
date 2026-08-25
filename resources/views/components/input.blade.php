@props([
    'disabled' => false,
    'required' => false,
    'name' => null,
    'type' => 'text',
    'icon' => null,
    'iconPosition' => 'left',
    'hasError' => false,
    'togglePassword' => false,
])

@php
    $hasValidationError = $name && isset($errors) && $errors->has($name);
    $errorState = $hasError || $hasValidationError;
    
    $borderClasses = $errorState
        ? 'border-rose-500 dark:border-rose-600 focus:border-rose-500 focus:ring-rose-500/20 text-rose-900 dark:text-rose-100'
        : 'border-slate-300 dark:border-slate-700 focus:border-blue-500 focus:ring-blue-500/20 text-slate-900 dark:text-white';
        
    $paddingClasses = $icon
        ? ($iconPosition === 'left' ? 'ps-10 pe-3.5' : 'ps-3.5 pe-10')
        : ($togglePassword ? 'ps-3.5 pe-11' : 'px-3.5');
@endphp

@if ($type === 'password' && $togglePassword)
    <div x-data="{ showPassword: false }" class="relative w-full">
        @if ($icon && $iconPosition === 'left')
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center ps-3 text-slate-400 dark:text-slate-500">
                <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-4.5 h-4.5" />
            </div>
        @endif

        <input :type="showPassword ? 'text' : 'password'"
            {{ $disabled ? 'disabled' : '' }}
            @if ($name) name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" @endif
            {!! $attributes->merge([
                'class' => "block w-full appearance-none rounded-lg border bg-white dark:bg-slate-950 py-2.5 sm:py-2 text-sm placeholder-slate-400 transition-colors duration-150 focus:outline-none focus:ring-2 {$borderClasses} {$paddingClasses} " .
                    ($disabled ? 'bg-slate-100 dark:bg-slate-900/60 cursor-not-allowed opacity-75' : ''),
            ]) !!}
            @if ($required) required @endif>

        <button 
            type="button" 
            @click="showPassword = !showPassword"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 focus:outline-none p-1"
            aria-label="Toggle password visibility"
        >
            <x-heroicon-o-eye x-show="!showPassword" class="w-4.5 h-4.5" />
            <x-heroicon-o-eye-slash x-show="showPassword" class="w-4.5 h-4.5" style="display: none;" />
        </button>
    </div>
@else
    <div class="relative w-full">
        @if ($icon && $iconPosition === 'left')
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center ps-3 text-slate-400 dark:text-slate-500">
                <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-4.5 h-4.5" />
            </div>
        @endif

        <input type="{{ $type }}"
            {{ $disabled ? 'disabled' : '' }}
            @if ($name) name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" @endif
            {!! $attributes->merge([
                'class' => "block w-full appearance-none rounded-lg border bg-white dark:bg-slate-950 py-2.5 sm:py-2 text-sm placeholder-slate-400 transition-colors duration-150 focus:outline-none focus:ring-2 {$borderClasses} {$paddingClasses} " .
                    ($disabled ? 'bg-slate-100 dark:bg-slate-900/60 cursor-not-allowed opacity-75' : ''),
            ]) !!}
            @if ($required) required @endif>

        @if ($icon && $iconPosition === 'right')
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pe-3 text-slate-400 dark:text-slate-500">
                <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-4.5 h-4.5" />
            </div>
        @endif
    </div>
@endif

@if ($hasValidationError)
    <p class="mt-1.5 text-xs font-medium text-rose-600 dark:text-rose-400 flex items-center gap-1">
        <x-heroicon-s-exclamation-circle class="w-3.5 h-3.5 shrink-0" />
        <span>{{ $errors->first($name) }}</span>
    </p>
@endif
