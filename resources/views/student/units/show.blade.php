@extends('layouts.app')

@section('title', $learningUnit->title.' — '.config('app.name', 'BisaBelajar'))

@section('content')
<div class="space-y-8">
    <x-page-header 
        :title="$learningUnit->title" 
        :description="$learningUnit->description ? 'Completion is not mastery. — '.$learningUnit->description : 'Completion is not mastery.'"
    >
        <x-slot name="breadcrumbs">
            <a href="{{ route('student.courses.show', $course) }}" class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors truncate max-w-[100px] sm:max-w-[150px]">{{ $course->title }}</a>
            <x-heroicon-m-chevron-right class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 shrink-0" />
            <a href="{{ route('student.modules.show', [$course, $module]) }}" class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors truncate max-w-[100px] sm:max-w-[150px]">{{ $module->title }}</a>
            <x-heroicon-m-chevron-right class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 shrink-0" />
            <span class="font-medium text-slate-700 dark:text-slate-200 truncate">{{ $learningUnit->title }}</span>
        </x-slot>

        <x-slot name="badge">
            <x-badge variant="{{ $progress->isCompleted() ? 'success' : 'warning' }}" dot>{{ strtoupper($progress->status->value) }}</x-badge>
        </x-slot>

        @if (! $progress->isCompleted())
            <x-slot name="actions">
                <form action="{{ route('student.progress.complete', [$course, $module, $learningUnit]) }}" method="POST">
                    @csrf
                    <x-button type="submit" variant="primary" icon="check">Mark unit complete</x-button>
                </form>
            </x-slot>
        @endif
    </x-page-header>

    <div class="space-y-4">
        <div class="flex items-center gap-2">
            <h2 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-slate-100">Materials</h2>
            <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400 border border-blue-200/60 dark:border-blue-800/60">
                {{ $learningUnit->materials->count() }}
            </span>
        </div>

        @if($learningUnit->materials->isEmpty())
            <div class="flex flex-col items-center justify-center py-12 sm:py-16 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 mb-4">
                    <x-heroicon-o-document-text class="w-7 h-7 text-slate-400 dark:text-slate-500" />
                </div>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">No published materials yet.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($learningUnit->materials as $material)
                    <x-card>
                        <h3 class="text-base sm:text-lg font-bold leading-snug text-slate-900 dark:text-white mb-2">
                            <a href="{{ route('materials.show', [$course, $learningUnit, $material]) }}" class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">
                                {{ $material->title }}
                            </a>
                        </h3>
                        <x-badge variant="info" size="sm">{{ strtoupper($material->type->value) }}</x-badge>
                    </x-card>
                @endforeach
            </div>
        @endif
    </div>

    <div class="space-y-4">
        <div class="flex items-center gap-2">
            <h2 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-slate-100">Activities</h2>
            <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400 border border-blue-200/60 dark:border-blue-800/60">
                {{ $learningUnit->activities->count() }}
            </span>
        </div>

        @if($learningUnit->activities->isEmpty())
            <div class="flex flex-col items-center justify-center py-12 sm:py-16 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 mb-4">
                    <x-heroicon-o-puzzle-piece class="w-7 h-7 text-slate-400 dark:text-slate-500" />
                </div>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">No published activities yet.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($learningUnit->activities as $activity)
                    @php
                        $activityStatus = \App\Models\ActivityProgress::statusFor($activityProgressById[$activity->id] ?? null);
                    @endphp
                    <x-card>
                        <h3 class="text-base sm:text-lg font-bold leading-snug text-slate-900 dark:text-white mb-2">
                            <a href="{{ route('activities.show', [$course, $learningUnit, $activity]) }}" class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">
                                {{ $activity->title }}
                            </a>
                        </h3>
                        <div class="flex gap-2 flex-wrap">
                            <x-badge variant="gray" size="sm">{{ strtoupper($activity->type->value) }}</x-badge>
                            <x-badge variant="{{ $activityStatus === \App\Enums\ProgressStatus::Completed ? 'success' : ($activityStatus === \App\Enums\ProgressStatus::InProgress ? 'warning' : 'gray') }}" size="sm" dot>{{ strtoupper($activityStatus->value) }}</x-badge>
                        </div>
                    </x-card>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
