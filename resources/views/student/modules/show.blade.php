@extends('layouts.app')

@section('title', $module->title.' — '.config('app.name', 'BisaBelajar'))

@section('content')
<div class="space-y-8">
    <x-page-header 
        :title="$module->title" 
        :description="$module->description"
    >
        <x-slot name="breadcrumbs">
            <a href="{{ route('student.dashboard') }}" class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">Dashboard</a>
            <x-heroicon-m-chevron-right class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 shrink-0" />
            <a href="{{ route('student.courses') }}" class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">My Courses</a>
            <x-heroicon-m-chevron-right class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 shrink-0" />
            <a href="{{ route('student.courses.show', $course) }}" class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors truncate max-w-[120px] sm:max-w-[200px]">{{ $course->title }}</a>
            <x-heroicon-m-chevron-right class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 shrink-0" />
            <span class="font-medium text-slate-700 dark:text-slate-200 truncate">{{ $module->title }}</span>
        </x-slot>
    </x-page-header>

    <div class="space-y-4">
        <div class="flex items-center gap-2">
            <h2 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-slate-100">Learning units</h2>
            <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400 border border-blue-200/60 dark:border-blue-800/60">
                {{ $module->learningUnits->count() }}
            </span>
        </div>

        @if($module->learningUnits->isEmpty())
            <div class="flex flex-col items-center justify-center py-12 sm:py-16 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 mb-4">
                    <x-heroicon-o-document-text class="w-7 h-7 text-slate-400 dark:text-slate-500" />
                </div>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">No published learning units yet.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($module->learningUnits as $unit)
                    <x-card>
                        <h3 class="text-base sm:text-lg font-bold leading-snug text-slate-900 dark:text-white mb-2">
                            <a href="{{ route('student.units.show', [$course, $module, $unit]) }}" class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">
                                {{ $unit->title }}
                            </a>
                        </h3>
                        <div class="mb-3">
                            <x-badge variant="gray" size="sm" dot>{{ strtoupper(\App\Models\LearningProgress::statusFor($progressByUnitId->get($unit->id))->value) }}</x-badge>
                        </div>
                        @if($unit->description)
                            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">{{ Str::limit($unit->description, 120) }}</p>
                        @endif
                    </x-card>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
