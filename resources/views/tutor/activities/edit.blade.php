@extends('layouts.app')

@section('title', 'Edit activity — '.$activity->title.' — '.config('app.name'))

@section('content')
<div class="space-y-8">
    <x-page-header 
        title="Edit activity" 
        description="Course: {{ $course->title }} · Unit: {{ $learningUnit->title }}"
    >
        <x-slot name="breadcrumbs">
            <a href="{{ route('tutor.workspace') }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400 transition-colors">Tutor workspace</a>
            <x-heroicon-m-chevron-right class="h-4 w-4 text-slate-400 dark:text-slate-600 shrink-0" />
            <a href="{{ route('tutor.courses.edit', $course) }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400 transition-colors truncate max-w-[100px] sm:max-w-xs">{{ $course->title }}</a>
            <x-heroicon-m-chevron-right class="h-4 w-4 text-slate-400 dark:text-slate-600 shrink-0" />
            <a href="{{ route('tutor.modules.edit', [$course, $module]) }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400 transition-colors truncate max-w-[100px] sm:max-w-xs">{{ $module->title }}</a>
            <x-heroicon-m-chevron-right class="h-4 w-4 text-slate-400 dark:text-slate-600 shrink-0" />
            <a href="{{ route('tutor.units.edit', [$course, $module, $learningUnit]) }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400 transition-colors truncate max-w-[100px] sm:max-w-xs">{{ $learningUnit->title }}</a>
            <x-heroicon-m-chevron-right class="h-4 w-4 text-slate-400 dark:text-slate-600 shrink-0" />
            <span class="text-slate-400 dark:text-slate-500 truncate">Edit activity</span>
        </x-slot>

        <x-slot name="badge">
            <x-badge variant="{{ $activity->status->value === 'published' ? 'success' : 'warning' }}" dot>
                {{ strtoupper($activity->status->value) }}
            </x-badge>
            <x-badge variant="secondary">
                {{ strtoupper($activity->type->value) }}
            </x-badge>
        </x-slot>

        <x-slot name="actions">
            <x-button variant="outline" size="sm" href="{{ route('tutor.units.edit', [$course, $module, $learningUnit]) }}" icon="arrow-left">
                Back to activities
            </x-button>
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-8">
            <x-card>
                <div class="flex items-center justify-between pb-4 mb-6 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white">Activity Details</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Konfigurasi instruksi dan tipe aktivitas pembelajaran.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('tutor.activities.update', [$course, $module, $learningUnit, $activity]) }}" class="space-y-5">
                    @csrf
                    @method('PUT')
                    @include('tutor.activities.partials.form')

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <x-button variant="primary" type="submit" icon="check">Save activity</x-button>
                    </div>
                </form>
            </x-card>
        </div>

        <div class="lg:col-span-1 space-y-6">
            <x-card title="Ringkasan Aktivitas">
                <x-description-list>
                    <x-description-item label="Status">
                        <x-badge variant="{{ $activity->status->value === 'published' ? 'success' : 'warning' }}" dot>
                            {{ strtoupper($activity->status->value) }}
                        </x-badge>
                    </x-description-item>
                    <x-description-item label="Tipe">
                        <x-badge variant="secondary">
                            {{ strtoupper($activity->type->value) }}
                        </x-badge>
                    </x-description-item>
                    <x-description-item label="Aturan Selesai" :value="strtoupper($activity->completionRule()->value)" />
                    <x-description-item label="Induk Unit">
                        <a href="{{ route('tutor.units.edit', [$course, $module, $learningUnit]) }}" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline truncate max-w-[150px]">
                            {{ $learningUnit->title }}
                        </a>
                    </x-description-item>
                </x-description-list>
            </x-card>

            <x-card title="Actions">
                <div class="space-y-3">
                    <form method="POST" action="{{ route('tutor.activities.publish', [$course, $module, $learningUnit, $activity]) }}" class="w-full">
                        @csrf
                        <x-button variant="success" type="submit" class="w-full justify-center" icon="check-circle">Publish</x-button>
                    </form>
                    <form method="POST" action="{{ route('tutor.activities.unpublish', [$course, $module, $learningUnit, $activity]) }}" class="w-full">
                        @csrf
                        <x-button variant="secondary" type="submit" class="w-full justify-center">Unpublish</x-button>
                    </form>

                    @if ($activity->status->value !== 'archived')
                        <form method="POST" action="{{ route('tutor.activities.archive', [$course, $module, $learningUnit, $activity]) }}" class="w-full" onsubmit="return confirm('Archive this activity?')">
                            @csrf
                            <x-button variant="secondary" type="submit" class="w-full justify-center" icon="archive-box">Archive</x-button>
                        </form>
                    @endif
                </div>
            </x-card>
        </div>
    </div>
</div>
@endsection
