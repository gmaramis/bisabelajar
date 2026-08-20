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
        <p class="mt-4 text-sm text-slate-500">Completion rule: {{ strtoupper($activity->completionRule()->value) }} · Activity completion is not unit progress or mastery.</p>
    </article>

    @if (auth()->user()?->isStudent() && $module)
        @php
            $startStatus = \App\Models\ActivityProgress::statusFor($activityProgress ?? null);
        @endphp
        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <p class="mb-3 text-sm text-slate-500">{{ strtoupper($startStatus->value) }} · Activity completion is not unit progress or mastery.</p>
            @if ($errors->has('completion'))
                <div class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ $errors->first('completion') }}
                </div>
            @endif
            @if ($startStatus === \App\Enums\ProgressStatus::NotStarted)
                <form method="POST" action="{{ route('student.activities.start', [$course, $module, $learningUnit, $activity]) }}">
                    @csrf
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Start activity</button>
                </form>
            @elseif ($startStatus !== \App\Enums\ProgressStatus::Completed)
                <form method="POST" action="{{ route('student.activities.complete', [$course, $module, $learningUnit, $activity]) }}">
                    @csrf
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Mark activity complete</button>
                </form>
            @endif
        </section>

        @php
            $submissions = $submissions ?? collect();
            $remainingAttempts = $activity->maxAttempts() - $submissions->count();
            $canSubmit = $startStatus !== \App\Enums\ProgressStatus::NotStarted && $remainingAttempts > 0;
        @endphp

        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-2 text-lg font-semibold">Submission</h2>
            <p class="mb-3 text-sm text-slate-500">Attempt {{ $submissions->count() }}/{{ $activity->maxAttempts() }} · Submission is not a grade.</p>

            @if ($errors->any())
                <div class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            @if ($canSubmit)
                <form method="POST" action="{{ route('student.activities.submit', [$course, $module, $learningUnit, $activity]) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label for="payload_body" class="mb-1 block text-sm font-medium">Your response</label>
                        <textarea id="payload_body" name="payload[body]" rows="5" required class="w-full rounded-md border border-slate-300 px-3 py-2">{{ old('payload.body') }}</textarea>
                    </div>
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Submit</button>
                </form>
            @elseif ($startStatus !== \App\Enums\ProgressStatus::NotStarted)
                <p class="text-sm text-slate-600">No remaining attempts.</p>
            @endif

            @if ($submissions->isNotEmpty())
                <ol class="mt-4 space-y-3">
                    @foreach ($submissions as $submission)
                        <li class="rounded-md border border-slate-200 p-3 text-sm">
                            <p class="text-slate-500">Attempt {{ $submission->attempt_number }} · Version {{ $submission->version }} · {{ strtoupper($submission->status->value) }}</p>
                            <p class="mt-1 whitespace-pre-wrap text-slate-700">{{ $submission->payload['body'] ?? '' }}</p>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    @endif
@endsection
