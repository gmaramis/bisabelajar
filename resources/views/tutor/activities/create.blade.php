@extends('layouts.app')

@section('title', 'Add activity — '.config('app.name'))

@section('content')
    <h1 class="mb-1 text-xl font-semibold">Add activity</h1>
    <p class="mb-4 text-sm text-slate-600">{{ $course->title }} · {{ $module->title }} · {{ $learningUnit->title }}</p>

    <form method="POST" action="{{ route('tutor.activities.store', [$course, $module, $learningUnit]) }}" class="max-w-xl space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @csrf

        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <div>
            <label for="title" class="mb-1 block text-sm font-medium">Title</label>
            <input id="title" name="title" type="text" value="{{ old('title') }}" required class="w-full rounded-md border border-slate-300 px-3 py-2">
        </div>

        <div>
            <label for="type" class="mb-1 block text-sm font-medium">Type</label>
            <select id="type" name="type" class="w-full rounded-md border border-slate-300 px-3 py-2">
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ strtoupper($type->value) }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="instructions" class="mb-1 block text-sm font-medium">Student instructions</label>
            <textarea id="instructions" name="configuration[instructions]" rows="4" required class="w-full rounded-md border border-slate-300 px-3 py-2">{{ old('configuration.instructions') }}</textarea>
        </div>

        <div>
            <label for="prompt" class="mb-1 block text-sm font-medium">Discussion prompt</label>
            <textarea id="prompt" name="configuration[prompt]" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2">{{ old('configuration.prompt') }}</textarea>
            <p class="mt-1 text-xs text-slate-500">Required for DISCUSSION. Leave empty for other types.</p>
        </div>

        <div>
            <label for="max_attempts" class="mb-1 block text-sm font-medium">Max attempts</label>
            <input id="max_attempts" name="configuration[max_attempts]" type="number" min="1" max="20" value="{{ old('configuration.max_attempts') }}" class="w-full rounded-md border border-slate-300 px-3 py-2">
            <p class="mt-1 text-xs text-slate-500">Optional for QUIZ and EXAM.</p>
        </div>

        <div>
            <label for="time_limit_minutes" class="mb-1 block text-sm font-medium">Time limit (minutes)</label>
            <input id="time_limit_minutes" name="configuration[time_limit_minutes]" type="number" min="1" max="600" value="{{ old('configuration.time_limit_minutes') }}" class="w-full rounded-md border border-slate-300 px-3 py-2">
            <p class="mt-1 text-xs text-slate-500">Optional for QUIZ and EXAM.</p>
        </div>

        <div>
            <label for="language" class="mb-1 block text-sm font-medium">Language</label>
            <input id="language" name="configuration[language]" type="text" value="{{ old('configuration.language') }}" class="w-full rounded-md border border-slate-300 px-3 py-2">
            <p class="mt-1 text-xs text-slate-500">Optional for CODING_EXERCISE. Code is not executed here.</p>
        </div>

        <div>
            <label for="tutor_notes" class="mb-1 block text-sm font-medium">Tutor notes (private)</label>
            <textarea id="tutor_notes" name="configuration[tutor][notes]" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2">{{ old('configuration.tutor.notes') }}</textarea>
        </div>

        <div>
            <label for="tutor_answer_key" class="mb-1 block text-sm font-medium">Answer key (private)</label>
            <textarea id="tutor_answer_key" name="configuration[tutor][answer_key]" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2">{{ old('configuration.tutor.answer_key') }}</textarea>
            <p class="mt-1 text-xs text-slate-500">Optional for QUIZ and EXAM. Not scored automatically.</p>
        </div>

        <div>
            <label for="tutor_rubric" class="mb-1 block text-sm font-medium">Rubric (private)</label>
            <textarea id="tutor_rubric" name="configuration[tutor][rubric]" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2">{{ old('configuration.tutor.rubric') }}</textarea>
            <p class="mt-1 text-xs text-slate-500">Optional for ASSIGNMENT and PROJECT. Not used for grading here.</p>
        </div>

        <div>
            <label for="tutor_expected_output" class="mb-1 block text-sm font-medium">Expected output (private)</label>
            <textarea id="tutor_expected_output" name="configuration[tutor][expected_output]" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2">{{ old('configuration.tutor.expected_output') }}</textarea>
            <p class="mt-1 text-xs text-slate-500">Optional for CODING_EXERCISE. Student code is not executed.</p>
        </div>

        <p class="text-sm text-slate-600">Tutor-private configuration is never shown to students. Quiz scoring, assignment grading, and code execution are not enabled.</p>

        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Save activity</button>
    </form>
@endsection
