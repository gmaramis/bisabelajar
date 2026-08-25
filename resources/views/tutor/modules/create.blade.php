@extends('layouts.app')

@section('title', 'Add module — '.$course->title.' — '.config('app.name'))

@section('content')
<div class="space-y-8 max-w-3xl mx-auto">
    <x-page-header 
        title="Add module" 
        description="Course: {{ $course->title }}"
    >
        <x-slot name="breadcrumbs">
            <a href="{{ route('tutor.workspace') }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400 transition-colors">Tutor workspace</a>
            <x-heroicon-m-chevron-right class="h-4 w-4 text-slate-400 dark:text-slate-600 shrink-0" />
            <a href="{{ route('tutor.courses.edit', $course) }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400 transition-colors truncate max-w-xs">{{ $course->title }}</a>
            <x-heroicon-m-chevron-right class="h-4 w-4 text-slate-400 dark:text-slate-600 shrink-0" />
            <span class="text-slate-400 dark:text-slate-500">Add module</span>
        </x-slot>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('tutor.modules.store', $course) }}" class="space-y-6">
            @csrf
            @include('tutor.modules.partials.form')

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                <x-button variant="outline" href="{{ route('tutor.courses.edit', $course) }}">Batal</x-button>
                <x-button variant="primary" type="submit" icon="plus">Create module</x-button>
            </div>
        </form>
    </x-card>
</div>
@endsection
