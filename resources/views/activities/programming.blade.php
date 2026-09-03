@extends('layouts.app')

@section('title', $activity->title.' — '.config('app.name'))

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.47.0/min/vs/loader.js"></script>
@endpush

@section('content')
    <x-page-header 
        :title="$activity->title" 
        :description="!empty($configuration['instructions']) ? $configuration['instructions'] : null"
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
            <x-badge variant="info">{{ strtoupper($activity->completionRule()->value) }}</x-badge>
        </x-slot>
    </x-page-header>

    <x-card class="mt-6">

        @if (! empty($programmingActivity))
            <div class="mt-4 flex flex-wrap gap-3">
                @if (! empty($programmingActivity['execution_time_limit_seconds']))
                    <div class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                        <x-heroicon-o-clock class="w-4 h-4 shrink-0" />
                        <span>{{ $programmingActivity['execution_time_limit_seconds'] }}s time limit</span>
                    </div>
                @endif
                @if (! empty($programmingActivity['memory_limit_mb']))
                    <div class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                        <x-heroicon-o-cpu-chip class="w-4 h-4 shrink-0" />
                        <span>{{ $programmingActivity['memory_limit_mb'] }} MB memory</span>
                    </div>
                @endif
                @if (! empty($programmingActivity['source_code_size_limit_kb']))
                    <div class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                        <x-heroicon-o-document-text class="w-4 h-4 shrink-0" />
                        <span>Max {{ $programmingActivity['source_code_size_limit_kb'] }} KB</span>
                    </div>
                @endif
            </div>
        @endif

        <p class="mt-4 text-xs text-slate-500 dark:text-slate-400 italic">Activity completion is not unit progress or mastery.</p>
    </x-card>

    @if (auth()->user()?->isStudent() && $module)
        @php
            $startStatus = \App\Models\ActivityProgress::statusFor($activityProgress ?? null);
        @endphp

        <x-card class="mb-6" bodyClass="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-2">
                <x-badge variant="{{ $startStatus === \App\Enums\ProgressStatus::Completed ? 'success' : ($startStatus === \App\Enums\ProgressStatus::InProgress ? 'warning' : 'gray') }}" dot>
                    {{ strtoupper($startStatus->value) }}
                </x-badge>
            </div>

            @if ($errors->has('completion'))
                <x-alert variant="danger" class="w-full">{{ $errors->first('completion') }}</x-alert>
            @endif

            <div class="flex gap-2">
                @if ($startStatus === \App\Enums\ProgressStatus::NotStarted)
                    <form method="POST" action="{{ route('student.activities.start', [$course, $module, $learningUnit, $activity]) }}">
                        @csrf
                        <x-button variant="primary" type="submit" icon="play">Mulai Aktivitas</x-button>
                    </form>
                @elseif ($startStatus !== \App\Enums\ProgressStatus::Completed)
                    <form method="POST" action="{{ route('student.activities.complete', [$course, $module, $learningUnit, $activity]) }}">
                        @csrf
                        <x-button variant="success" type="submit" icon="check">Tandai Selesai</x-button>
                    </form>
                @endif
            </div>
        </x-card>

        @php
            $submissions = $submissions ?? collect();
            $remainingAttempts = $activity->maxAttempts() - $submissions->count();
            $canSubmit = $startStatus !== \App\Enums\ProgressStatus::NotStarted && $remainingAttempts > 0;
        @endphp

        @if (!empty($programmingActivity))
            <div class="overflow-hidden rounded-xl sm:rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900 mb-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-100 dark:border-slate-800 px-4 py-3 sm:px-6 sm:py-4">
                    <h2 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">Code Editor</h2>
                    <div class="flex items-center gap-3">
                        <x-select name="language" id="language-select" class="!py-1.5 !text-xs !min-w-[140px]">
                            @foreach($availableProfiles as $profile)
                                <option value="{{ $profile['id'] }}" {{ $profile['id'] == $programmingActivity['language_execution_profile_id'] ? 'selected' : '' }}>
                                    {{ $profile['display_name'] }}
                                </option>
                            @endforeach
                        </x-select>
                        <span id="language-hint" class="text-xs text-slate-500 dark:text-slate-400 hidden sm:inline"></span>
                    </div>
                </div>

                <div id="monaco-editor" class="w-full border-b border-slate-200 dark:border-slate-800" style="height: 450px;"></div>

                <div id="editor-status" class="flex items-center justify-between text-[10px] sm:text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950/60 px-4 py-1.5 border-b border-slate-100 dark:border-slate-800 font-mono">
                    <span id="cursor-position">Ln 1, Col 1</span>
                    <span id="language-mode">Python</span>
                    <span id="execution-status"></span>
                </div>

                <div class="flex flex-wrap items-center gap-2 px-4 py-3 sm:px-6 sm:py-4 bg-white dark:bg-slate-900">
                    <button id="run-button" type="button" onclick="runCode()" class="inline-flex items-center justify-center gap-2 font-semibold text-xs sm:text-sm min-h-[44px] sm:min-h-[40px] rounded-lg px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 active:bg-blue-800 transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed select-none active:scale-[0.98]">
                        <x-heroicon-s-play class="w-4 h-4" />
                        <span>Run</span>
                    </button>

                    <button id="submit-button" type="button" onclick="submitCode()" {{ $canSubmit ? '' : 'disabled' }} class="inline-flex items-center justify-center gap-2 font-semibold text-xs sm:text-sm min-h-[44px] sm:min-h-[40px] rounded-lg px-4 py-2 bg-emerald-600 text-white hover:bg-emerald-700 active:bg-emerald-800 transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed select-none active:scale-[0.98]">
                        <x-heroicon-s-paper-airplane class="w-4 h-4" />
                        <span>Submit</span>
                    </button>

                    <button id="format-button" type="button" onclick="formatCode()" class="inline-flex items-center justify-center gap-2 font-semibold text-xs sm:text-sm min-h-[44px] sm:min-h-[40px] rounded-lg px-4 py-2 bg-white border border-slate-300 text-slate-700 shadow-xs hover:bg-slate-50 transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700 select-none">
                        <x-heroicon-o-code-bracket class="w-4 h-4" />
                        <span class="hidden sm:inline">Format</span>
                    </button>

                    <div id="nexus-hint-trigger" class="hidden">
                        <button
                            type="button"
                            onclick="window.dispatchEvent(new CustomEvent('nexus-hint', { detail: { errorMessage: window.__lastErrorMessage ?? null, testLabel: window.__lastTestLabel ?? null } }))"
                            class="inline-flex items-center justify-center gap-2 font-semibold text-xs sm:text-sm min-h-[44px] sm:min-h-[40px] rounded-lg px-4 py-2 bg-sky-600 text-white hover:bg-sky-700 active:bg-sky-800 transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 select-none active:scale-[0.98] shadow-xs"
                        >
                            <x-heroicon-o-light-bulb class="w-4 h-4" />
                            <span>Petunjuk NEXUS</span>
                        </button>
                    </div>

                    <div class="ml-auto text-xs text-slate-500 dark:text-slate-400">
                        <span id="execution-time"></span>
                    </div>
                </div>

                <div class="border-t border-slate-200 dark:border-slate-800">
                    <div class="flex items-center justify-between px-4 py-2.5 bg-slate-900 dark:bg-slate-950 border-b border-slate-700 dark:border-slate-800">
                        <h3 class="text-xs font-bold text-slate-300 uppercase tracking-wider">Output</h3>
                        <button id="clear-output" type="button" class="text-xs text-slate-500 hover:text-slate-300 transition-colors font-medium">Clear</button>
                    </div>
                    <div id="output-content" class="p-4 font-mono text-xs sm:text-sm text-slate-100 bg-slate-900 dark:bg-slate-950 min-h-[120px] max-h-[300px] overflow-auto whitespace-pre-wrap custom-scrollbar"></div>
                </div>

                <div id="test-results-panel" class="border-t border-slate-200 dark:border-slate-800 hidden">
                    <div class="flex items-center justify-between px-4 py-2.5 bg-slate-900 dark:bg-slate-950 border-b border-slate-700 dark:border-slate-800">
                        <h3 class="text-xs font-bold text-slate-300 uppercase tracking-wider">Test Results</h3>
                        <span id="test-summary" class="text-xs text-slate-400"></span>
                    </div>
                    <div id="test-results-content" class="p-4 space-y-2 max-h-[300px] overflow-auto bg-slate-900 dark:bg-slate-950 custom-scrollbar"></div>
                </div>

                @if (auth()->user()?->isStudent() && $module && $course)
                    <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-800">
                        <x-ai-hint-panel
                            :hint-url="route('student.activities.ai-hint', [$course, $module, $learningUnit, $activity])"
                            :attempt-count="$submissions->count() + 1"
                        />
                    </div>
                @endif
            </div>
        @endif

        @if (empty($programmingActivity))
            <x-card title="Pengumpulan" class="mb-6">
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mb-4">Percobaan {{ $submissions->count() }}/{{ $activity->maxAttempts() }} · Submission is not a grade.</p>

                @if ($errors->any())
                    <x-alert variant="danger" class="mb-4">{{ $errors->first() }}</x-alert>
                @endif

                @if ($canSubmit)
                    <form method="POST" action="{{ route('student.activities.submit', [$course, $module, $learningUnit, $activity]) }}" class="space-y-4">
                        @csrf
                        <x-form-group label="Jawaban Anda" name="payload[body]" required>
                            <x-textarea name="payload[body]" rows="5" required placeholder="Tulis jawaban Anda di sini...">{{ old('payload.body') }}</x-textarea>
                        </x-form-group>
                        <x-button variant="primary" type="submit" icon="paper-airplane">Kirim Jawaban</x-button>
                    </form>
                @elseif ($startStatus !== \App\Enums\ProgressStatus::NotStarted)
                    <p class="text-sm text-slate-500 dark:text-slate-400">Tidak ada sisa percobaan.</p>
                @endif

                @if ($submissions->isNotEmpty())
                    <div class="mt-6 space-y-3">
                        @foreach ($submissions as $submission)
                            <div class="rounded-xl border border-slate-200 dark:border-slate-800 p-3 sm:p-4">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <x-badge variant="gray" size="sm">Percobaan {{ $submission->attempt_number }}</x-badge>
                                    <x-badge variant="info" size="sm">v{{ $submission->version }}</x-badge>
                                    <x-badge variant="{{ $submission->status->value === 'completed' ? 'success' : 'warning' }}" size="sm" dot>{{ strtoupper($submission->status->value) }}</x-badge>
                                </div>
                                <p class="whitespace-pre-wrap text-xs sm:text-sm text-slate-700 dark:text-slate-300">{{ $submission->payload['body'] ?? '' }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>
        @endif

        @if (!empty($programmingActivity))
            <x-card title="Riwayat Eksekusi">
                <div id="execution-history" class="space-y-2">
                    <div class="flex items-center justify-center py-8">
                        <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                            <x-heroicon-o-arrow-path class="w-4 h-4 animate-spin" />
                            <span>Memuat riwayat...</span>
                        </div>
                    </div>
                </div>
            </x-card>
        @endif
    @endif
@endsection

@push('scripts')
<script>
    let editor = null;
    let currentLanguage = 'python';
    let selectedProfileId = {{ $programmingActivity['language_execution_profile_id'] ?? $availableProfiles->first()['id'] ?? 0 }};
    const availableProfiles = @json($availableProfiles);
    const starterCode = @json($programmingActivity['starter_code'] ?? '');
    const editableFiles = @json($programmingActivity['editable_files'] ?? ['main' => 'main.py']);
    const runUrl = "{{ route('student.activities.programming.run', [$course, $module, $learningUnit, $activity]) }}";
    const submitUrl = "{{ route('student.activities.programming.submit', [$course, $module, $learningUnit, $activity]) }}";
    const historyUrl = "{{ route('student.activities.programming.history', [$course, $module, $learningUnit, $activity]) }}";
    const csrfToken = "{{ csrf_token() }}";

    const languageMap = {
        'python': 'python',
        'javascript': 'javascript',
        'typescript': 'typescript',
        'java': 'java',
        'cpp': 'cpp',
        'c': 'c',
        'go': 'go',
        'rust': 'rust',
    };

    const fileExtensionMap = {
        'python': 'py',
        'javascript': 'js',
        'typescript': 'ts',
        'java': 'java',
        'cpp': 'cpp',
        'c': 'c',
        'go': 'go',
        'rust': 'rs',
    };

    require.config({ paths: { vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.47.0/min/vs' }});

    require(['vs/editor/editor.main'], function() {
        const profile = availableProfiles.find(p => p.id === selectedProfileId);
        currentLanguage = profile ? (languageMap[profile.identifier] || profile.identifier) : 'python';

        const isDark = document.documentElement.classList.contains('dark');

        editor = monaco.editor.create(document.getElementById('monaco-editor'), {
            value: starterCode,
            language: currentLanguage,
            theme: isDark ? 'vs-dark' : 'vs',
            automaticLayout: true,
            minimap: { enabled: false },
            fontSize: 14,
            lineNumbers: 'on',
            scrollBeyondLastLine: false,
            tabSize: 4,
            insertSpaces: true,
            wordWrap: 'on',
            bracketPairColorization: { enabled: true },
            renderLineHighlight: 'all',
            folding: true,
            matchBrackets: 'always',
            autoClosingBrackets: 'always',
            autoClosingQuotes: 'always',
            formatOnPaste: true,
            formatOnType: true,
            padding: { top: 12, bottom: 12 },
        });

        editor.onDidChangeCursorPosition(e => {
            document.getElementById('cursor-position').textContent = `Ln ${e.position.lineNumber}, Col ${e.position.column}`;
        });

        editor.onDidChangeModelLanguage(e => {
            currentLanguage = e.newLanguage;
            document.getElementById('language-mode').textContent = e.newLanguage.charAt(0).toUpperCase() + e.newLanguage.slice(1);
        });

        document.getElementById('language-mode').textContent = currentLanguage.charAt(0).toUpperCase() + currentLanguage.slice(1);

        loadHistory();
    });

    document.getElementById('language-select').addEventListener('change', function(e) {
        selectedProfileId = parseInt(e.target.value);
        const profile = availableProfiles.find(p => p.id === selectedProfileId);
        if (profile && editor) {
            const newLanguage = languageMap[profile.identifier] || profile.identifier;
            monaco.editor.setModelLanguage(editor.getModel(), newLanguage);
            currentLanguage = newLanguage;
        }
    });

    function formatCode() {
        if (editor) {
            editor.getAction('editor.action.formatDocument').run();
        }
    }

    document.getElementById('clear-output').addEventListener('click', function() {
        document.getElementById('output-content').textContent = '';
        document.getElementById('execution-time').textContent = '';
        document.getElementById('test-results-panel').classList.add('hidden');
    });

    async function runCode() {
        if (!editor) return;

        const sourceCode = editor.getValue();
        if (!sourceCode.trim()) {
            showOutput('No code to run.', 'error');
            return;
        }

        setRunningState(true);
        showOutput('Executing in isolated sandbox...', 'info');

        try {
            const response = await fetch(runUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    source_code: sourceCode,
                    language_execution_profile_id: selectedProfileId,
                }),
            });

            const data = await response.json();
            handleExecutionResult(data, 'run');
        } catch (error) {
            showOutput('Network error: ' + error.message, 'error');
        } finally {
            setRunningState(false);
        }
    }

    async function submitCode() {
        if (!editor) return;

        const sourceCode = editor.getValue();
        if (!sourceCode.trim()) {
            showOutput('No code to submit.', 'error');
            return;
        }

        if (!confirm('Submit this code for evaluation? This will count as an attempt.')) {
            return;
        }

        setRunningState(true);
        showOutput('Submitting for evaluation...', 'info');

        try {
            const response = await fetch(submitUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    source_code: sourceCode,
                    language_execution_profile_id: selectedProfileId,
                }),
            });

            const data = await response.json();
            handleExecutionResult(data, 'submit');
        } catch (error) {
            showOutput('Network error: ' + error.message, 'error');
        } finally {
            setRunningState(false);
        }
    }

    function handleExecutionResult(data, type) {
        if (!data.success) {
            showOutput(data.error || 'Execution failed', 'error');
            return;
        }

        const status = data.status;
        const stdout = data.stdout || '';
        const stderr = data.stderr || '';
        const compileError = data.compile_error;
        const runtimeError = data.runtime_error;
        const timeout = data.timeout;
        const execTime = data.execution_duration_ms;
        const testSummary = data.test_summary;

        document.getElementById('execution-time').textContent = `${execTime}ms`;

        let output = '';
        let outputType = 'info';

        if (compileError) {
            output = `Compile Error:\n${compileError}`;
            outputType = 'error';
        } else if (runtimeError) {
            output = `Runtime Error:\n${runtimeError}`;
            outputType = 'error';
        } else if (timeout) {
            output = 'Execution timed out';
            outputType = 'error';
        } else if (stderr) {
            output = `Stderr:\n${stderr}\n\nStdout:\n${stdout}`;
            outputType = 'warning';
        } else {
            output = stdout || '(no output)';
            outputType = 'success';
        }

        showOutput(output, outputType);

        if (testSummary) {
            showTestResults(testSummary, type === 'submit');
        }

        const statusEl = document.getElementById('execution-status');
        const statusLabels = {
            'success': 'Success',
            'compile_error': 'Compile Error',
            'runtime_error': 'Runtime Error',
            'timeout': 'Timeout',
            'system_error': 'System Error',
            'resource_limit': 'Resource Limit',
        };
        statusEl.textContent = statusLabels[status] || status;
        statusEl.className = 'px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider ' +
            (status === 'success' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400');

        if (compileError || runtimeError || (testSummary && testSummary.passed < testSummary.total)) {
            window.__lastErrorMessage = compileError || runtimeError || (testSummary ? `Passed ${testSummary.passed}/${testSummary.total} tests` : null);
            const trigger = document.getElementById('nexus-hint-trigger');
            if (trigger) trigger.classList.remove('hidden');
        }

        loadHistory();
    }

    function showOutput(text, type) {
        const el = document.getElementById('output-content');
        el.textContent = text;
        el.className = 'p-4 font-mono text-xs sm:text-sm min-h-[120px] max-h-[300px] overflow-auto whitespace-pre-wrap custom-scrollbar bg-slate-900 dark:bg-slate-950 ' +
            (type === 'error' ? 'text-rose-300' : type === 'warning' ? 'text-amber-300' : type === 'success' ? 'text-emerald-300' : 'text-slate-100');
    }

    function showTestResults(summary, isSubmit) {
        const panel = document.getElementById('test-results-panel');
        const content = document.getElementById('test-results-content');
        const summaryEl = document.getElementById('test-summary');

        const total = summary.total || 0;
        const passed = summary.passed || 0;
        const visiblePassed = summary.visible_passed || 0;
        const visibleTotal = summary.visible_total || 0;
        const hiddenPassed = summary.hidden_passed || 0;
        const hiddenTotal = summary.hidden_total || 0;

        summaryEl.textContent = `${passed}/${total} passed`;

        let html = '';

        if (visibleTotal > 0) {
            html += `<div class="border-l-2 border-blue-500 pl-3">
                <p class="font-semibold text-xs text-blue-300">Visible Tests: ${visiblePassed}/${visibleTotal}</p>
            </div>`;
        }

        if (hiddenTotal > 0) {
            html += `<div class="border-l-2 border-purple-500 pl-3 mt-2">
                <p class="font-semibold text-xs text-purple-300">Hidden Tests: ${hiddenPassed}/${hiddenTotal}</p>
            </div>`;
        }

        if (isSubmit && data && typeof data.passes_evaluation !== 'undefined') {
            const passes = data.passes_evaluation;
            html += `<div class="mt-3 p-3 rounded-lg ${passes ? 'bg-emerald-900/30 border-emerald-700' : 'bg-rose-900/30 border-rose-700'} border">
                <p class="font-semibold text-xs ${passes ? 'text-emerald-300' : 'text-rose-300'}">
                    ${passes ? 'Submission PASSED evaluation' : 'Submission FAILED evaluation'}
                </p>
            </div>`;
        }

        content.innerHTML = html;
        panel.classList.remove('hidden');
    }

    function setRunningState(running) {
        document.getElementById('run-button').disabled = running;
        document.getElementById('submit-button').disabled = running;
        document.getElementById('format-button').disabled = running;
    }

    async function loadHistory() {
        try {
            const response = await fetch(historyUrl, {
                headers: { 'Accept': 'application/json' },
            });
            const data = await response.json();

            if (data.success) {
                const historyEl = document.getElementById('execution-history');
                if (data.executions.data.length === 0) {
                    historyEl.innerHTML = '<div class="flex flex-col items-center justify-center py-8 text-center"><div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 mb-3"><svg class="w-6 h-6 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></div><p class="text-sm font-medium text-slate-500 dark:text-slate-400">Belum ada riwayat eksekusi.</p></div>';
                } else {
                    historyEl.innerHTML = data.executions.data.map(exec => `
                        <div class="rounded-xl border border-slate-200 dark:border-slate-800 p-3 sm:p-4 transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-800/50">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-mono text-xs sm:text-sm font-medium text-slate-700 dark:text-slate-200">${exec.language}</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border ${exec.status === 'success' ? 'bg-emerald-50 text-emerald-700 border-emerald-200/60 dark:bg-emerald-950/60 dark:text-emerald-400 dark:border-emerald-800/60' : 'bg-rose-50 text-rose-700 border-rose-200/60 dark:bg-rose-950/60 dark:text-rose-400 dark:border-rose-800/60'}">
                                    ${exec.status}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">${exec.created_at}</p>
                            ${exec.test_summary ? `<p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Tests: ${exec.test_summary.passed}/${exec.test_summary.total} passed</p>` : ''}
                        </div>
                    `).join('');
                }
            }
        } catch (error) {
            console.error('Failed to load history:', error);
        }
    }
</script>
@endpush