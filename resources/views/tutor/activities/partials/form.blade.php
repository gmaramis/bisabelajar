@php
    $activity = $activity ?? null;
    $config = $activity?->configuration ?? [];
    $tutorConfig = $config['tutor'] ?? [];
@endphp

@if ($errors->any())
    <x-alert variant="danger" class="mb-4">
        {{ $errors->first() }}
    </x-alert>
@endif

<div class="space-y-6">
    <div class="space-y-4">
        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Pengaturan Dasar</h3>

        <x-form-group label="Title" name="title" required>
            <x-input id="title" name="title" type="text" value="{{ old('title', $activity?->title) }}" required placeholder="e.g. Kuis Dasar Python" />
        </x-form-group>

        <x-form-group label="Type" name="type" required>
            <x-select id="type" name="type">
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(old('type', $activity?->type?->value) === $type->value)>{{ strtoupper($type->value) }}</option>
                @endforeach
            </x-select>
        </x-form-group>

        <x-form-group label="Student instructions" name="configuration[instructions]" required>
            <x-textarea id="instructions" name="configuration[instructions]" rows="4" required placeholder="Petunjuk pengerjaan bagi siswa...">{{ old('configuration.instructions', $config['instructions'] ?? '') }}</x-textarea>
        </x-form-group>
    </div>

    <div class="space-y-4 pt-4 border-t border-slate-100 dark:border-slate-800">
        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Konfigurasi Tipe Aktivitas</h3>

        <div data-activity-fields="discussion">
            <x-form-group label="Discussion prompt" name="configuration[prompt]" help="Wajib diisi untuk tipe DISCUSSION.">
                <x-textarea id="prompt" name="configuration[prompt]" rows="3" placeholder="Topik diskusi...">{{ old('configuration.prompt', $config['prompt'] ?? '') }}</x-textarea>
            </x-form-group>
        </div>

        <div data-activity-fields="quiz exam">
            <x-form-group label="Max attempts" name="configuration[max_attempts]">
                <x-input id="max_attempts" name="configuration[max_attempts]" type="number" min="1" max="20" value="{{ old('configuration.max_attempts', $config['max_attempts'] ?? '') }}" placeholder="e.g. 3" />
            </x-form-group>
        </div>

        <div data-activity-fields="quiz exam">
            <x-form-group label="Time limit (minutes)" name="configuration[time_limit_minutes]">
                <x-input id="time_limit_minutes" name="configuration[time_limit_minutes]" type="number" min="1" max="600" value="{{ old('configuration.time_limit_minutes', $config['time_limit_minutes'] ?? '') }}" placeholder="e.g. 60" />
            </x-form-group>
        </div>

        <div data-activity-fields="coding_exercise">
            <x-form-group label="Language" name="configuration[language]" help="Opsional untuk CODING_EXERCISE. Code is not executed here.">
                <x-input id="language" name="configuration[language]" type="text" value="{{ old('configuration.language', $config['language'] ?? '') }}" placeholder="e.g. python" />
            </x-form-group>
        </div>

        <x-form-group label="Completion rule" name="configuration[completion_rule]" help="Activity completion is not unit progress or mastery.">
            <x-select id="completion_rule" name="configuration[completion_rule]">
                <option value="{{ \App\Enums\CompletionRule::Submission->value }}" @selected(old('configuration.completion_rule', $config['completion_rule'] ?? \App\Enums\CompletionRule::Submission->value) === \App\Enums\CompletionRule::Submission->value)>Submission required</option>
                <option value="{{ \App\Enums\CompletionRule::Manual->value }}" @selected(old('configuration.completion_rule', $config['completion_rule'] ?? '') === \App\Enums\CompletionRule::Manual->value)>Manual</option>
            </x-select>
        </x-form-group>
    </div>

    <div class="space-y-4 pt-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/40 p-4 rounded-xl border border-slate-200/60 dark:border-slate-800/60">
        <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                <x-heroicon-s-lock-closed class="w-4 h-4 text-amber-500" />
                <span>Tutor-Private Configuration</span>
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Tutor-private configuration is never shown to students. Quiz scoring, assignment grading, and code execution are not enabled.</p>
        </div>

        <x-form-group label="Tutor notes (private)" name="configuration[tutor][notes]">
            <x-textarea id="tutor_notes" name="configuration[tutor][notes]" rows="3" placeholder="Catatan internal pengajar...">{{ old('configuration.tutor.notes', $tutorConfig['notes'] ?? '') }}</x-textarea>
        </x-form-group>

        <div data-activity-fields="quiz exam">
            <x-form-group label="Answer key (private)" name="configuration[tutor][answer_key]" help="Optional for QUIZ and EXAM. Not scored automatically.">
                <x-textarea id="tutor_answer_key" name="configuration[tutor][answer_key]" rows="3" placeholder="Kunci jawaban internal...">{{ old('configuration.tutor.answer_key', $tutorConfig['answer_key'] ?? '') }}</x-textarea>
            </x-form-group>
        </div>

        <div data-activity-fields="assignment project">
            <x-form-group label="Rubric (private)" name="configuration[tutor][rubric]" help="Optional for ASSIGNMENT and PROJECT. Not used for grading here.">
                <x-textarea id="tutor_rubric" name="configuration[tutor][rubric]" rows="3" placeholder="Rubrik penilaian internal...">{{ old('configuration.tutor.rubric', $tutorConfig['rubric'] ?? '') }}</x-textarea>
            </x-form-group>
        </div>

        <div data-activity-fields="coding_exercise">
            <x-form-group label="Expected output (private)" name="configuration[tutor][expected_output]" help="Optional for CODING_EXERCISE. Student code is not executed.">
                <x-textarea id="tutor_expected_output" name="configuration[tutor][expected_output]" rows="3" placeholder="Output ekspektasi internal...">{{ old('configuration.tutor.expected_output', $tutorConfig['expected_output'] ?? '') }}</x-textarea>
            </x-form-group>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const type = document.getElementById('type');
        if (! type) return;
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