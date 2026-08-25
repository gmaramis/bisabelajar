@extends('layouts.app')

@section('title', $course->title.' — '.config('app.name', 'BisaBelajar'))

@section('content')
<div class="space-y-8 max-w-4xl mx-auto">
    <x-page-header 
        :title="$course->title" 
        :description="$course->description"
    >
        <x-slot name="breadcrumbs">
            <a href="{{ route('courses.index') }}" class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">Courses</a>
            <x-heroicon-m-chevron-right class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 shrink-0" />
            <span class="font-medium text-slate-700 dark:text-slate-200 truncate">{{ $course->title }}</span>
        </x-slot>

        <x-slot name="badge">
            <x-badge :variant="$course->visibility->value === 'public' ? 'success' : 'secondary'">
                {{ strtoupper($course->visibility->value) }}
            </x-badge>
        </x-slot>

        @auth
            @if (auth()->user()->isTutor() && $course->isOwnedBy(auth()->user()))
                <x-slot name="actions">
                    <x-button variant="secondary" size="sm" href="{{ route('tutor.courses.edit', $course) }}" icon="pencil-square">
                        Edit course
                    </x-button>
                </x-slot>
            @endif
        @endauth
    </x-page-header>

    @if ($errors->has('course'))
        <x-alert variant="danger">
            {{ $errors->first('course') }}
        </x-alert>
    @endif

    <x-card>
        <div class="space-y-6">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white mb-2">Tentang Kursus Ini</h2>
                <p class="whitespace-pre-wrap text-xs sm:text-sm text-slate-700 dark:text-slate-300 leading-relaxed">
                    {{ $course->description }}
                </p>
            </div>

            <div class="pt-6 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                @if ($isEnrolled)
                    <div class="flex items-center gap-2 text-xs sm:text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                        <x-heroicon-s-check-circle class="w-5 h-5" />
                        <span>You are enrolled in this course.</span>
                    </div>
                    <x-button variant="primary" href="{{ route('student.courses.show', $course) }}">
                        Buka Ruang Belajar
                    </x-button>
                @elseif ($canEnroll)
                    <form method="POST" action="{{ route('enrollments.store', $course) }}">
                        @csrf
                        <x-button type="submit" variant="primary" icon="academic-cap">
                            Enroll
                        </x-button>
                    </form>
                @endif
            </div>
        </div>
    </x-card>
</div>
@endsection
