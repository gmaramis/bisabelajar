<?php

namespace App\Models;

use App\Enums\ActivityType;
use App\Models\Activity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'activity_id',
    'language_execution_profile_id',
    'starter_code',
    'editable_files',
    'execution_time_limit_seconds',
    'memory_limit_mb',
    'source_code_size_limit_kb',
    'submission_rules',
    'evaluation_config',
])]
class ProgrammingActivity extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'editable_files' => 'array',
            'execution_time_limit_seconds' => 'integer',
            'memory_limit_mb' => 'integer',
            'source_code_size_limit_kb' => 'integer',
            'submission_rules' => 'array',
            'evaluation_config' => 'array',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function languageExecutionProfile(): BelongsTo
    {
        return $this->belongsTo(LanguageExecutionProfile::class);
    }

    public function testCases(): HasMany
    {
        return $this->hasMany(TestCase::class)->orderBy('sort_order');
    }

    public function visibleTestCases(): HasMany
    {
        return $this->testCases()->where('visible', true);
    }

    public function hiddenTestCases(): HasMany
    {
        return $this->testCases()->where('visible', false);
    }

    public function codeExecutions(): HasMany
    {
        return $this->hasMany(CodeExecution::class);
    }

    public function getEditableFiles(): array
    {
        return $this->editable_files ?? [
            'main' => $this->languageExecutionProfile?->defaultSourceFilename() ?? 'main.py',
        ];
    }

    public function getSubmissionRules(): array
    {
        return $this->submission_rules ?? [
            'max_submissions' => 10,
            'allowed_languages' => [$this->language_execution_profile_id],
        ];
    }

    public function getEvaluationConfig(): array
    {
        return $this->evaluation_config ?? [
            'pass_threshold' => 1.0, // 100% of visible tests must pass
            'require_all_hidden' => true,
        ];
    }

    public function getExecutionTimeLimitSeconds(): int
    {
        return $this->execution_time_limit_seconds ?? 30;
    }

    public function getMemoryLimitMb(): int
    {
        return $this->memory_limit_mb ?? 256;
    }

    public function getSourceCodeSizeLimitKb(): int
    {
        return $this->source_code_size_limit_kb ?? 64;
    }

    public static function createForActivity(Activity $activity, LanguageExecutionProfile $profile, array $options = []): self
    {
        return static::create([
            'activity_id' => $activity->id,
            'language_execution_profile_id' => $profile->id,
            'starter_code' => $options['starter_code'] ?? null,
            'editable_files' => $options['editable_files'] ?? null,
            'execution_time_limit_seconds' => $options['execution_time_limit_seconds'] ?? 30,
            'memory_limit_mb' => $options['memory_limit_mb'] ?? 256,
            'source_code_size_limit_kb' => $options['source_code_size_limit_kb'] ?? 64,
            'submission_rules' => $options['submission_rules'] ?? null,
            'evaluation_config' => $options['evaluation_config'] ?? null,
        ]);
    }
}