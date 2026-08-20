@extends('layouts.app')

@section('title', $activity->title.' — '.config('app.name'))

@section('content')
    @if (auth()->user()?->isStudent() && $module)
        <nav class="mb-4 flex flex-wrap gap-2 text-sm text-slate-600">
            <a href="{{ route('student.courses.show', $course) }}" class="underline">{{ $course->title }}</a>
            <span>/</span>
            <a href="{{ route('student.modules.show', [$course, $module]) }}" class="underline">{{ $module->title }}</a>
            <span>/</span>
            <a href="{{ route('student.units.show', [$course, $module, $learningUnit]) }}" class="underline">{{ $learningUnit->title }}</a>
        </nav>
    @endif

    @if (session('status'))
        <p class="mb-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{{ session('status') }}</p>
    @endif

    <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
        <h1 class="mb-2 text-xl font-semibold sm:text-2xl">{{ $activity->title }}</h1>
        <p class="mb-4 text-sm text-slate-500">{{ strtoupper($activity->type->value) }}</p>

        @if (! empty($configuration['instructions']))
            <div class="whitespace-pre-wrap text-sm text-slate-700">{{ $configuration['instructions'] }}</div>
        @endif

        @if (! empty($configuration['prompt']))
            <p class="mt-4 text-sm text-slate-700"><span class="font-medium">Prompt:</span> {{ $configuration['prompt'] }}</p>
        @endif

        @if (! empty($configuration['max_attempts']) || ! empty($configuration['time_limit_minutes']) || ! empty($configuration['language']))
            <ul class="mt-4 space-y-1 text-sm text-slate-600">
                @if (! empty($configuration['max_attempts']))
                    <li>Max attempts: {{ $configuration['max_attempts'] }}</li>
                @endif
                @if (! empty($configuration['time_limit_minutes']))
                    <li>Time limit: {{ $configuration['time_limit_minutes'] }} minutes</li>
                @endif
                @if (! empty($configuration['language']))
                    <li>Language: {{ $configuration['language'] }}</li>
                @endif
            </ul>
        @endif
    </article>

    @if (auth()->user()?->isStudent() && $module)
        @php
            $startStatus = \App\Models\ActivityProgress::statusFor($activityProgress ?? null);
        @endphp
        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <p class="mb-3 text-sm text-slate-500">{{ strtoupper($startStatus->value) }} · Start is not completion or mastery.</p>
            @if ($startStatus === \App\Enums\ProgressStatus::NotStarted)
                <form method="POST" action="{{ route('student.activities.start', [$course, $module, $learningUnit, $activity]) }}">
                    @csrf
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Start activity</button>
                </form>
            @endif
        </section>
    @endif
@endsection
