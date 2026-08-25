@extends('layouts.app')

@section('title', 'Add learning unit — '.$module->title.' — '.config('app.name'))

@section('content')
<div class="space-y-8 max-w-3xl mx-auto">
    <x-page-header 
        title="Add learning unit" 
        description="Course: {{ $course->title }} · Module: {{ $module->title }}"
    >
        <x-slot name="breadcrumbs">
            <a href="{{ route('tutor.workspace') }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400 transition-colors">Tutor workspace</a>
            <x-heroicon-m-chevron-right class="h-4 w-4 text-slate-400 dark:text-slate-600 shrink-0" />
            <a href="{{ route('tutor.courses.edit', $course) }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400 transition-colors truncate max-w-[120px] sm:max-w-xs">{{ $course->title }}</a>
            <x-heroicon-m-chevron-right class="h-4 w-4 text-slate-400 dark:text-slate-600 shrink-0" />
            <a href="{{ route('tutor.modules.edit', [$course, $module]) }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400 transition-colors truncate max-w-[120px] sm:max-w-xs">{{ $module->title }}</a>
            <x-heroicon-m-chevron-right class="h-4 w-4 text-slate-400 dark:text-slate-600 shrink-0" />
            <span class="text-slate-400 dark:text-slate-500">Add learning unit</span>
        </x-slot>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('tutor.units.store', [$course, $module]) }}" class="space-y-6">
            @csrf
            @include('tutor.learning-units.partials.form')

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                <x-button variant="outline" href="{{ route('tutor.modules.edit', [$course, $module]) }}">Batal</x-button>
                <x-button variant="primary" type="submit" icon="plus">Create unit</x-button>
            </div>
        </form>
    </x-card>
</div>
@endsection
