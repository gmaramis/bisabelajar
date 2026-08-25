@extends('layouts.app')

@section('title', $activity->title.' — '.config('app.name', 'BisaBelajar'))

@section('content')
<div class="space-y-8 max-w-4xl mx-auto">
    <x-page-header 
        :title="$activity->title" 
        description="Completion rule: {{ strtoupper($activity->completionRule()->value) }} · Activity completion is not unit progress or mastery."
    >
        @if (auth()->user()?->isStudent() && $module)
            <x-slot name="breadcrumbs">
                <a href="{{ route('student.courses.show', $course) }}" class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">{{ $course->title }}</a>
                <x-heroicon-m-chevron-right class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 shrink-0" />
                <a href="{{ route('student.modules.show', [$course, $module]) }}" class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">{{ $module->title }}</a>
                <x-heroicon-m-chevron-right class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 shrink-0" />
                <a href="{{ route('student.units.show', [$course, $module, $learningUnit]) }}" class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">{{ $learningUnit->title }}</a>
                <x-heroicon-m-chevron-right class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 shrink-0" />
                <span class="font-medium text-slate-700 dark:text-slate-200 truncate">{{ $activity->title }}</span>
            </x-slot>
        @endif

        <x-slot name="badge">
            <x-badge variant="primary" dot>{{ strtoupper($activity->type->value) }}</x-badge>
        </x-slot>
    </x-page-header>

    <x-card>
        <div class="space-y-4">
            @if (! empty($configuration['instructions']))
                <div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Petunjuk</h2>
                    <div class="whitespace-pre-wrap text-xs sm:text-sm text-slate-700 dark:text-slate-300 leading-relaxed">{{ $configuration['instructions'] }}</div>
                </div>
            @endif

            @if (! empty($configuration['prompt']))
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
                    <span class="text-xs sm:text-sm font-semibold text-slate-900 dark:text-white">Prompt:</span>
                    <p class="text-xs sm:text-sm text-slate-700 dark:text-slate-300 mt-1">{{ $configuration['prompt'] }}</p>
                </div>
            @endif

            @if (! empty($configuration['max_attempts']) || ! empty($configuration['time_limit_minutes']) || ! empty($configuration['language']))
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex flex-wrap gap-3">
                    @if (! empty($configuration['max_attempts']))
                        <div class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                            <x-heroicon-o-arrow-path class="w-4 h-4 shrink-0" />
                            <span>Max attempts: {{ $configuration['max_attempts'] }}</span>
                        </div>
                    @endif
                    @if (! empty($configuration['time_limit_minutes']))
                        <div class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                            <x-heroicon-o-clock class="w-4 h-4 shrink-0" />
                            <span>Time limit: {{ $configuration['time_limit_minutes'] }} minutes</span>
                        </div>
                    @endif
                    @if (! empty($configuration['language']))
                        <div class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                            <x-heroicon-o-code-bracket class="w-4 h-4 shrink-0" />
                            <span>Language: {{ $configuration['language'] }}</span>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </x-card>

    @if (auth()->user()?->isStudent() && $module)
        @php
            $startStatus = \App\Models\ActivityProgress::statusFor($activityProgress ?? null);
        @endphp

        <x-card>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">{{ strtoupper($startStatus->value) }} · Activity completion is not unit progress or mastery.</p>
                    <div class="mt-2">
                        <x-badge variant="{{ $startStatus === \App\Enums\ProgressStatus::Completed ? 'success' : ($startStatus === \App\Enums\ProgressStatus::InProgress ? 'warning' : 'gray') }}" dot>
                            {{ strtoupper($startStatus->value) }}
                        </x-badge>
                    </div>
                </div>

                @if ($errors->has('completion'))
                    <x-alert variant="danger" class="w-full">{{ $errors->first('completion') }}</x-alert>
                @endif

                <div class="flex gap-2">
                    @if ($startStatus === \App\Enums\ProgressStatus::NotStarted)
                        <form method="POST" action="{{ route('student.activities.start', [$course, $module, $learningUnit, $activity]) }}">
                            @csrf
                            <x-button variant="primary" type="submit" icon="play">Start activity</x-button>
                        </form>
                    @elseif ($startStatus !== \App\Enums\ProgressStatus::Completed)
                        <form method="POST" action="{{ route('student.activities.complete', [$course, $module, $learningUnit, $activity]) }}">
                            @csrf
                            <x-button variant="success" type="submit" icon="check">Mark activity complete</x-button>
                        </form>
                    @endif
                </div>
            </div>
        </x-card>

        @php
            $submissions = $submissions ?? collect();
            $remainingAttempts = $activity->maxAttempts() - $submissions->count();
            $canSubmit = $startStatus !== \App\Enums\ProgressStatus::NotStarted && $remainingAttempts > 0;
        @endphp

        <x-card title="Submission">
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mb-4">Attempt {{ $submissions->count() }}/{{ $activity->maxAttempts() }} · Submission is not a grade.</p>

            @if ($errors->any())
                <x-alert variant="danger" class="mb-4">{{ $errors->first() }}</x-alert>
            @endif

            @if ($canSubmit)
                <form method="POST" action="{{ route('student.activities.submit', [$course, $module, $learningUnit, $activity]) }}" class="space-y-4">
                    @csrf
                    <x-form-group label="Your response" name="payload[body]" required>
                        <x-textarea name="payload[body]" rows="5" required placeholder="Write your response...">{{ old('payload.body') }}</x-textarea>
                    </x-form-group>
                    <x-button variant="primary" type="submit" icon="paper-airplane">Submit</x-button>
                </form>
            @elseif ($startStatus !== \App\Enums\ProgressStatus::NotStarted)
                <p class="text-sm text-slate-500 dark:text-slate-400">No remaining attempts.</p>
            @endif

            @if ($submissions->isNotEmpty())
                <div class="mt-6 space-y-3">
                    @foreach ($submissions as $submission)
                        <div class="rounded-xl border border-slate-200 dark:border-slate-800 p-3 sm:p-4">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Attempt {{ $submission->attempt_number }} · Version {{ $submission->version }} · {{ strtoupper($submission->status->value) }}</p>
                            <p class="mt-2 whitespace-pre-wrap text-xs sm:text-sm text-slate-700 dark:text-slate-300 leading-relaxed">{{ $submission->payload['body'] ?? '' }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>
    @endif
</div>
@endsection
