@extends('layouts.app')

@section('title', 'My Courses — '.config('app.name', 'BisaBelajar'))

@section('content')
<div class="space-y-8">
    <x-page-header 
        title="My Courses" 
        description="Active enrollments for {{ $user->name }}."
    >
        <x-slot name="actions">
            <x-button variant="secondary" size="sm" href="{{ route('courses.index') }}">Course catalog</x-button>
        </x-slot>
    </x-page-header>

    @if($enrollments->isEmpty())
        <div class="flex flex-col items-center justify-center py-12 sm:py-16 text-center">
            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 mb-4">
                <x-heroicon-o-academic-cap class="w-7 h-7 text-slate-400 dark:text-slate-500" />
            </div>
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">You have no active enrollments yet.</p>
            <x-button variant="primary" size="sm" href="{{ route('courses.index') }}" class="mt-4">Course catalog</x-button>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($enrollments as $enrollment)
                <x-card>
                    <h3 class="text-base sm:text-lg font-bold leading-snug text-slate-900 dark:text-white mb-2">
                        <a href="{{ route('student.courses.show', $enrollment->course) }}" class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">
                            {{ $enrollment->course->title }}
                        </a>
                    </h3>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mb-4">{{ Str::limit($enrollment->course->description, 100) }}</p>
                    <x-badge variant="primary" dot>{{ strtoupper($enrollment->status->value) }}</x-badge>
                </x-card>
            @endforeach
        </div>

        @if ($enrollments instanceof \Illuminate\Contracts\Pagination\Paginator && $enrollments->hasPages())
            <div class="pt-6 border-t border-slate-200/80 dark:border-slate-800">
                <x-pagination :paginator="$enrollments" />
            </div>
        @endif
    @endif
</div>
@endsection
