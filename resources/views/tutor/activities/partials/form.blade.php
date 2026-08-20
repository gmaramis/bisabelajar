@php
    $activity = $activity ?? null;
    $config = $activity?->configuration ?? [];
    $tutorConfig = $config['tutor'] ?? [];
@endphp

@if ($errors->any())
    <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ $errors->first() }}
    </div>
@endif

<div>
    <label for="title" class="mb-1 block text-sm font-medium">Title</label>
    <input id="title" name="title" type="text" value="{{ old('title', $activity?->title) }}" required class="w-full rounded-md border border-slate-300 px-3 py-2">
</div>

<div>
    <label for="type" class="mb-1 block text-sm font-medium">Type</label>
    <select id="type" name="type" class="w-full rounded-md border border-slate-300 px-3 py-2">
        @foreach ($types as $type)
            <option value="{{ $type->value }}" @selected(old('type', $activity?->type?->value) === $type->value)>{{ strtoupper($type->value) }}</option>
        @endforeach
    </select>
</div>

<div>
    <label for="instructions" class="mb-1 block text-sm font-medium">Student instructions</label>
    <textarea id="instructions" name="configuration[instructions]" rows="4" required class="w-full rounded-md border border-slate-300 px-3 py-2">{{ old('configuration.instructions', $config['instructions'] ?? '') }}</textarea>
</div>

<div data-activity-fields="discussion">
    <label for="prompt" class="mb-1 block text-sm font-medium">Discussion prompt</label>
    <textarea id="prompt" name="configuration[prompt]" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2">{{ old('configuration.prompt', $config['prompt'] ?? '') }}</textarea>
    <p class="mt-1 text-xs text-slate-500">Required for DISCUSSION.</p>
</div>

<div data-activity-fields="quiz exam">
    <label for="max_attempts" class="mb-1 block text-sm font-medium">Max attempts</label>
    <input id="max_attempts" name="configuration[max_attempts]" type="number" min="1" max="20" value="{{ old('configuration.max_attempts', $config['max_attempts'] ?? '') }}" class="w-full rounded-md border border-slate-300 px-3 py-2">
</div>

<div data-activity-fields="quiz exam">
    <label for="time_limit_minutes" class="mb-1 block text-sm font-medium">Time limit (minutes)</label>
    <input id="time_limit_minutes" name="configuration[time_limit_minutes]" type="number" min="1" max="600" value="{{ old('configuration.time_limit_minutes', $config['time_limit_minutes'] ?? '') }}" class="w-full rounded-md border border-slate-300 px-3 py-2">
</div>

<div data-activity-fields="coding_exercise">
    <label for="language" class="mb-1 block text-sm font-medium">Language</label>
    <input id="language" name="configuration[language]" type="text" value="{{ old('configuration.language', $config['language'] ?? '') }}" class="w-full rounded-md border border-slate-300 px-3 py-2">
    <p class="mt-1 text-xs text-slate-500">Optional for CODING_EXERCISE. Code is not executed here.</p>
</div>

<div>
    <label for="completion_rule" class="mb-1 block text-sm font-medium">Completion rule</label>
    <select id="completion_rule" name="configuration[completion_rule]" class="w-full rounded-md border border-slate-300 px-3 py-2">
        <option value="{{ \App\Enums\CompletionRule::Submission->value }}" @selected(old('configuration.completion_rule', $config['completion_rule'] ?? \App\Enums\CompletionRule::Submission->value) === \App\Enums\CompletionRule::Submission->value)>Submission required</option>
        <option value="{{ \App\Enums\CompletionRule::Manual->value }}" @selected(old('configuration.completion_rule', $config['completion_rule'] ?? '') === \App\Enums\CompletionRule::Manual->value)>Manual</option>
    </select>
    <p class="mt-1 text-xs text-slate-500">Activity completion is not unit progress or mastery.</p>
</div>

<div>
    <label for="tutor_notes" class="mb-1 block text-sm font-medium">Tutor notes (private)</label>
    <textarea id="tutor_notes" name="configuration[tutor][notes]" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2">{{ old('configuration.tutor.notes', $tutorConfig['notes'] ?? '') }}</textarea>
</div>

<div data-activity-fields="quiz exam">
    <label for="tutor_answer_key" class="mb-1 block text-sm font-medium">Answer key (private)</label>
    <textarea id="tutor_answer_key" name="configuration[tutor][answer_key]" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2">{{ old('configuration.tutor.answer_key', $tutorConfig['answer_key'] ?? '') }}</textarea>
    <p class="mt-1 text-xs text-slate-500">Optional for QUIZ and EXAM. Not scored automatically.</p>
</div>

<div data-activity-fields="assignment project">
    <label for="tutor_rubric" class="mb-1 block text-sm font-medium">Rubric (private)</label>
    <textarea id="tutor_rubric" name="configuration[tutor][rubric]" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2">{{ old('configuration.tutor.rubric', $tutorConfig['rubric'] ?? '') }}</textarea>
    <p class="mt-1 text-xs text-slate-500">Optional for ASSIGNMENT and PROJECT. Not used for grading here.</p>
</div>

<div data-activity-fields="coding_exercise">
    <label for="tutor_expected_output" class="mb-1 block text-sm font-medium">Expected output (private)</label>
    <textarea id="tutor_expected_output" name="configuration[tutor][expected_output]" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2">{{ old('configuration.tutor.expected_output', $tutorConfig['expected_output'] ?? '') }}</textarea>
    <p class="mt-1 text-xs text-slate-500">Optional for CODING_EXERCISE. Student code is not executed.</p>
</div>

<p class="text-sm text-slate-600">Tutor-private configuration is never shown to students. Quiz scoring, assignment grading, and code execution are not enabled.</p>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const type = document.getElementById('type');
        if (! type) {
            return;
        }

        const sync = function () {
            const current = type.value;
            document.querySelectorAll('[data-activity-fields]').forEach(function (group) {
                const show = group.getAttribute('data-activity-fields').split(/\s+/).includes(current);
                group.hidden = ! show;
                group.querySelectorAll('input, textarea, select').forEach(function (field) {
                    field.disabled = ! show;
                });
            });
        };

        type.addEventListener('change', sync);
        sync();
    });
</script>
