@extends('layouts.app')

@section('title', $activity->title.' — '.config('app.name'))

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.47.0/min/vs/loader.js"></script>
@endpush

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
            <div class="whitespace-pre-wrap text-sm text-slate-700 mb-4">{{ $configuration['instructions'] }}</div>
        @endif

        @if (! empty($programmingActivity))
            <div class="mb-4 space-y-1 text-sm text-slate-600">
                @if (! empty($programmingActivity['execution_time_limit_seconds']))
                    <p>Execution time limit: {{ $programmingActivity['execution_time_limit_seconds'] }} seconds</p>
                @endif
                @if (! empty($programmingActivity['memory_limit_mb']))
                    <p>Memory limit: {{ $programmingActivity['memory_limit_mb'] }} MB</p>
                @endif
                @if (! empty($programmingActivity['source_code_size_limit_kb']))
                    <p>Max source size: {{ $programmingActivity['source_code_size_limit_kb'] }} KB</p>
                @endif
            </div>
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

        @if (!empty($programmingActivity))
            {{-- Programming Activity Editor Section --}}
            <section class="mt-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-2 text-lg font-semibold">Code Editor</h2>
                
                <div class="space-y-3">
                    {{-- Language Selector --}}
                    <div class="flex items-center gap-4">
                        <label for="language-select" class="text-sm font-medium text-slate-700">Language:</label>
                        <select id="language-select" class="rounded-md border border-slate-300 px-3 py-2 text-sm" wire:model="selectedProfileId">
                            @foreach($availableProfiles as $profile)
                                <option value="{{ $profile['id'] }}" {{ $profile['id'] == $programmingActivity['language_execution_profile_id'] ? 'selected' : '' }}>
                                    {{ $profile['display_name'] }}
                                </option>
                            @endforeach
                        </select>
                        <span id="language-hint" class="text-sm text-slate-500"></span>
                    </div>

                    {{-- Monaco Editor Container --}}
                    <div id="monaco-editor" class="border border-slate-300 rounded-md overflow-hidden" style="height: 500px;"></div>
                    
                    {{-- Status Bar --}}
                    <div id="editor-status" class="flex items-center justify-between text-xs text-slate-500 bg-slate-50 px-3 py-2 rounded-md">
                        <span id="cursor-position">Ln 1, Col 1</span>
                        <span id="language-mode">Python</span>
                        <span id="execution-status"></span>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="flex flex-wrap gap-3">
                        <button 
                            id="run-button" 
                            type="button"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            wire:click="runCode"
                        >
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Run
                            </span>
                        </button>
                        
                        <button 
                            id="submit-button" 
                            type="button"
                            class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed"
                            wire:click="submitCode"
                            {{ $canSubmit ? '' : 'disabled' }}
                        >
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                Submit
                            </span>
                        </button>
                        
                        <button 
                            id="format-button" 
                            type="button"
                            class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                            wire:click="formatCode"
                        >
                            Format Code
                        </button>
                    </div>

                    {{-- Output Panel --}}
                    <div class="mt-4 rounded-md border border-slate-200 bg-slate-900 overflow-hidden">
                        <div class="flex items-center justify-between px-3 py-2 bg-slate-800 border-b border-slate-700">
                            <h3 class="text-sm font-medium text-slate-200">Output</h3>
                            <div class="flex items-center gap-2">
                                <span id="execution-time" class="text-xs text-slate-400"></span>
                                <button id="clear-output" type="button" class="text-xs text-slate-400 hover:text-slate-200">Clear</button>
                            </div>
                        </div>
                        <div id="output-content" class="p-3 font-mono text-sm text-slate-100 min-h-[150px] max-h-[300px] overflow-auto whitespace-pre-wrap"></div>
                    </div>

                    {{-- Test Results Panel --}}
                    <div id="test-results-panel" class="mt-4 hidden">
                        <div class="flex items-center justify-between px-3 py-2 bg-slate-800 border-b border-slate-700">
                            <h3 class="text-sm font-medium text-slate-200">Test Results</h3>
                            <span id="test-summary" class="text-xs text-slate-400"></span>
                        </div>
                        <div id="test-results-content" class="p-3 space-y-2 max-h-[300px] overflow-auto"></div>
                    </div>
                </div>
            </section>
        @endif

        {{-- Standard Submission Form (for non-programming activities) --}}
        @if (empty($programmingActivity))
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

        {{-- Execution History --}}
        @if (!empty($programmingActivity))
        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-2 text-lg font-semibold">Execution History</h2>
            <div id="execution-history" class="space-y-2">
                <p class="text-sm text-slate-500">Loading history...</p>
            </div>
        </section>
        @endif
    @endif
@endsection

@push('scripts')
<script>
    // Monaco Editor initialization
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

    // Language mapping
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

    // Initialize Monaco Editor
    require.config({ paths: { vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.47.0/min/vs' }});
    
    require(['vs/editor/editor.main'], function() {
        // Get initial language from selected profile
        const profile = availableProfiles.find(p => p.id === selectedProfileId);
        currentLanguage = profile ? (languageMap[profile.identifier] || profile.identifier) : 'python';
        
        editor = monaco.editor.create(document.getElementById('monaco-editor'), {
            value: starterCode,
            language: currentLanguage,
            theme: 'vs-dark',
            automaticLayout: true,
            minimap: { enabled: true },
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
        });

        // Update cursor position
        editor.onDidChangeCursorPosition(e => {
            document.getElementById('cursor-position').textContent = `Ln ${e.position.lineNumber}, Col ${e.position.column}`;
        });

        // Update language mode display
        editor.onDidChangeModelLanguage(e => {
            currentLanguage = e.newLanguage;
            document.getElementById('language-mode').textContent = e.newLanguage.charAt(0).toUpperCase() + e.newLanguage.slice(1);
        });

        // Set initial language mode display
        document.getElementById('language-mode').textContent = currentLanguage.charAt(0).toUpperCase() + currentLanguage.slice(1);

        // Load execution history
        loadHistory();
    });

    // Language selector handler
    document.getElementById('language-select').addEventListener('change', function(e) {
        selectedProfileId = parseInt(e.target.value);
        const profile = availableProfiles.find(p => p.id === selectedProfileId);
        if (profile && editor) {
            const newLanguage = languageMap[profile.identifier] || profile.identifier;
            monaco.editor.setModelLanguage(editor.getModel(), newLanguage);
            currentLanguage = newLanguage;
        }
    });

    // Format code
    function formatCode() {
        if (editor) {
            editor.getAction('editor.action.formatDocument').run();
        }
    }

    // Clear output
    document.getElementById('clear-output').addEventListener('click', function() {
        document.getElementById('output-content').textContent = '';
        document.getElementById('execution-time').textContent = '';
        document.getElementById('test-results-panel').classList.add('hidden');
    });

    // Run code
    async function runCode() {
        if (!editor) return;
        
        const sourceCode = editor.getValue();
        if (!sourceCode.trim()) {
            showOutput('No code to run.', 'error');
            return;
        }

        setRunningState(true);
        showOutput('Running...', 'info');

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

    // Submit code
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

    // Handle execution result
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

        // Build output
        let output = '';
        let outputType = 'info';

        if (compileError) {
            output = `❌ Compile Error:\n${compileError}`;
            outputType = 'error';
        } else if (runtimeError) {
            output = `❌ Runtime Error:\n${runtimeError}`;
            outputType = 'error';
        } else if (timeout) {
            output = '⏱️ Execution timed out';
            outputType = 'error';
        } else if (stderr) {
            output = `⚠️ Stderr:\n${stderr}\n\n✅ Stdout:\n${stdout}`;
            outputType = 'warning';
        } else {
            output = stdout || '(no output)';
            outputType = 'success';
        }

        showOutput(output, outputType);

        // Show test results if available
        if (testSummary) {
            showTestResults(testSummary, type === 'submit');
        }

        // Update execution status badge
        const statusEl = document.getElementById('execution-status');
        const statusLabels = {
            'success': '✅ Success',
            'compile_error': '❌ Compile Error',
            'runtime_error': '❌ Runtime Error',
            'timeout': '⏱️ Timeout',
            'system_error': '⚠️ System Error',
            'resource_limit': '📏 Resource Limit',
        };
        statusEl.textContent = statusLabels[status] || status;
        statusEl.className = 'px-2 py-1 rounded text-xs ' + 
            (status === 'success' ? 'bg-green-900 text-green-200' : 'bg-red-900 text-red-200');
        
        // Reload history
        loadHistory();
    }

    // Show output in panel
    function showOutput(text, type) {
        const el = document.getElementById('output-content');
        el.textContent = text;
        el.className = 'p-3 font-mono text-sm min-h-[150px] max-h-[300px] overflow-auto whitespace-pre-wrap ' + 
            (type === 'error' ? 'text-red-300' : type === 'warning' ? 'text-yellow-300' : type === 'success' ? 'text-green-300' : 'text-slate-100');
    }

    // Show test results
    function showTestResults(summary, isSubmit) {
        const panel = document.getElementById('test-results-panel');
        const content = document.getElementById('test-results-content');
        const summaryEl = document.getElementById('test-summary');

        const total = summary.total || 0;
        const passed = summary.passed || 0;
        const failed = summary.failed || 0;
        const visiblePassed = summary.visible_passed || 0;
        const visibleTotal = summary.visible_total || 0;
        const hiddenPassed = summary.hidden_passed || 0;
        const hiddenTotal = summary.hidden_total || 0;

        summaryEl.textContent = `${passed}/${total} passed`;
        
        let html = '';
        
        if (visibleTotal > 0) {
            html += `<div class="border-l-4 border-blue-500 pl-3">
                <p class="font-medium text-blue-300">Visible Tests: ${visiblePassed}/${visibleTotal}</p>`;
            // Individual test results would be shown here if we had them
            html += '</div>';
        }
        
        if (hiddenTotal > 0) {
            html += `<div class="border-l-4 border-purple-500 pl-3 mt-2">
                <p class="font-medium text-purple-300">Hidden Tests: ${hiddenPassed}/${hiddenTotal}</p>`;
            html += '</div>';
        }

        if (isSubmit) {
            const passes = data.passes_evaluation;
            html += `<div class="mt-3 p-3 rounded ${passes ? 'bg-green-900/30 border-green-700' : 'bg-red-900/30 border-red-700'} border">
                <p class="font-medium ${passes ? 'text-green-300' : 'text-red-300'}">
                    ${passes ? '✅ Submission PASSED evaluation' : '❌ Submission FAILED evaluation'}
                </p>
            </div>`;
        }

        content.innerHTML = html;
        panel.classList.remove('hidden');
    }

    // Set running state
    function setRunningState(running) {
        document.getElementById('run-button').disabled = running;
        document.getElementById('submit-button').disabled = running;
        document.getElementById('format-button').disabled = running;
    }

    // Load execution history
    async function loadHistory() {
        try {
            const response = await fetch(historyUrl, {
                headers: { 'Accept': 'application/json' },
            });
            const data = await response.json();
            
            if (data.success) {
                const historyEl = document.getElementById('execution-history');
                if (data.executions.data.length === 0) {
                    historyEl.innerHTML = '<p class="text-sm text-slate-500">No executions yet.</p>';
                } else {
                    historyEl.innerHTML = data.executions.data.map(exec => `
                        <div class="rounded-md border border-slate-200 p-3 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-slate-700">${exec.language}</span>
                                <span class="px-2 py-0.5 rounded text-xs ${exec.status === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                    ${exec.status}
                                </span>
                            </div>
                            <p class="mt-1 text-slate-500">${exec.created_at}</p>
                            ${exec.test_summary ? `<p class="mt-1 text-xs text-slate-500">Tests: ${exec.test_summary.passed}/${exec.test_summary.total} passed</p>` : ''}
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