@if ($errors->any())
    <div class="alert alert-error mb-6" role="alert">
        <svg class="alert-icon w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
            <p class="alert-title">Validation error</p>
            <p class="alert-description">{{ $errors->first() }}</p>
        </div>
    </div>
@endif

<div class="form-group">
    <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
    <input id="title" name="title" type="text" value="{{ old('title', $activity?->title ?? '') }}" required class="form-input" placeholder="Enter activity title">
    @error('title')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>

<div class="form-group">
    <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
    <select id="type" name="type" class="form-input" required>
        <option value="" disabled {{ !old('type') && !$activity?->type ? 'selected' : '' }}>Select activity type</option>
        @foreach ($types as $type)
            <option value="{{ $type->value }}" {{ (old('type') ?? $activity?->type?->value) === $type->value ? 'selected' : '' }}>
                {{ strtoupper($type->value) }}
            </option>
        @endforeach
    </select>
    @error('type')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>

<div class="form-group">
    <label for="description" class="form-label">Description</label>
    <textarea id="description" name="description" rows="4" class="form-input form-textarea" placeholder="Enter activity description">{{ old('description', $activity?->description ?? '') }}</textarea>
    @error('description')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>

<div class="form-group">
    <label for="content" class="form-label">Rich text content</label>
    <textarea id="content" name="content" rows="6" class="form-input form-textarea" placeholder="Enter rich text content (optional)">{{ old('content', $activity?->content ?? '') }}</textarea>
    <p class="form-hint">Optional rich text content. Supports Markdown formatting.</p>
    @error('content')
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>

@if ($activity?->type && $activity->type->value === 'programming')
    <div class="form-group">
        <label for="starter_code" class="form-label">Starter code</label>
        <textarea id="starter_code" name="starter_code" rows="10" class="form-input form-textarea font-mono" placeholder="Enter starter code for students">{{ old('starter_code', $activity?->starter_code ?? '') }}</textarea>
        <p class="form-hint">Initial code shown to students in the editor.</p>
        @error('starter_code')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="form-group">
        <label for="solution_code" class="form-label">Solution code</label>
        <textarea id="solution_code" name="solution_code" rows="10" class="form-input form-textarea font-mono" placeholder="Enter solution code for reference">{{ old('solution_code', $activity?->solution_code ?? '') }}</textarea>
        <p class="form-hint">Reference solution used for grading.</p>
        @error('solution_code')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="form-group">
        <label for="test_cases" class="form-label">Test cases (JSON)</label>
        <textarea id="test_cases" name="test_cases" rows="8" class="form-input form-textarea font-mono" placeholder="Enter test cases as JSON array">{{ old('test_cases', $activity?->test_cases ?? '') }}</textarea>
        <p class="form-hint">JSON array of test case objects: <code>{"input": "...", "expected_output": "..."}</code></p>
        @error('test_cases')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="form-group">
        <label for="language" class="form-label">Programming language</label>
        <select id="language" name="language" class="form-input form-select">
            @foreach ($languages as $lang)
                <option value="{{ $lang }}" {{ (old('language') ?? $activity?->language) === $lang ? 'selected' : '' }}>
                    {{ strtoupper($lang) }}
                </option>
            @endforeach
        </select>
        @error('language')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="form-group">
        <label for="time_limit" class="form-label">Time limit (seconds)</label>
        <input id="time_limit" name="time_limit" type="number" value="{{ old('time_limit', $activity?->time_limit ?? 2) }}" min="1" max="30" class="form-input form-input-sm" style="max-width: 8rem;">
        @error('time_limit')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="form-group">
        <label for="memory_limit" class="form-label">Memory limit (MB)</label>
        <input id="memory_limit" name="memory_limit" type="number" value="{{ old('memory_limit', $activity?->memory_limit ?? 128) }}" min="32" max="512" class="form-input form-input-sm" style="max-width: 8rem;">
        @error('memory_limit')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
@endif