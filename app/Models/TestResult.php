<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'code_execution_id',
    'test_case_id',
    'passed',
    'actual_output',
    'actual_error',
    'execution_duration_ms',
    'status',
    'metadata',
])]
class TestResult extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'passed' => 'boolean',
            'execution_duration_ms' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function codeExecution(): BelongsTo
    {
        return $this->belongsTo(CodeExecution::class);
    }

    public function testCase(): BelongsTo
    {
        return $this->belongsTo(TestCase::class);
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
        return $this->status === 'timeout';
    }
}