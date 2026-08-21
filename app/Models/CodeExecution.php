<?php

namespace App\Models;

use App\Enums\ActivityType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'course_id',
    'activity_id',
    'programming_activity_id',
    'language_execution_profile_id',
    'submission_id',
    'status',
    'source_code',
    'stdout',
    'stderr',
    'compile_error',
    'runtime_error',
    'timeout',
    'exit_code',
    'execution_duration_ms',
    'memory_used_kb',
    'resource_usage',
    'test_summary',
])]
class CodeExecution extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => 'string',
            'timeout' => 'boolean',
            'exit_code' => 'integer',
            'execution_duration_ms' => 'integer',
            'memory_used_kb' => 'integer',
            'resource_usage' => 'array',
            'test_summary' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function programmingActivity(): BelongsTo
    {
        return $this->belongsTo(ProgrammingActivity::class);
    }

    public function languageExecutionProfile(): BelongsTo
    {
        return $this->belongsTo(LanguageExecutionProfile::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ActivitySubmission::class, 'submission_id');
    }

    public function testResults(): HasMany
    {
        return $this->hasMany(TestResult::class);
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isCompileError(): bool
    {
        return $this->status === 'compile_error';
    }

    public function isRuntimeError(): bool
    {
        return $this->status === 'runtime_error';
    }

    public function isTimeout(): bool
    {
        return $this->status === 'timeout' || $this->timeout;
    }

    public function isMemoryLimit(): bool
    {
        return $this->status === 'memory_limit';
    }

    public function isResourceLimit(): bool
    {
        return $this->status === 'resource_limit';
    }

    public function hasError(): bool
    {
        return in_array($this->status, [
            'compile_error', 'runtime_error', 'timeout', 'memory_limit', 'resource_limit', 'system_error'
        ]);
    }

    public function getTestSummary(): array
    {
        return $this->test_summary ?? [
            'total' => 0,
            'passed' => 0,
            'failed' => 0,
            'visible_passed' => 0,
            'visible_total' => 0,
            'hidden_passed' => 0,
            'hidden_total' => 0,
        ];
    }
}