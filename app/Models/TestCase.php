<?php

namespace App\Models;

use App\Models\ProgrammingActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'programming_activity_id',
    'name',
    'description',
    'sort_order',
    'input',
    'expected_output',
    'comparison_strategy',
    'visible',
    'timeout_seconds',
    'memory_limit_mb',
    'metadata',
])]
class TestCase extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'visible' => 'boolean',
            'timeout_seconds' => 'integer',
            'memory_limit_mb' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function programmingActivity(): BelongsTo
    {
        return $this->belongsTo(ProgrammingActivity::class);
    }

    public function testResults(): HasMany
    {
        return $this->hasMany(TestResult::class);
    }

    public function getTimeoutSeconds(?int $default = null): int
    {
        return $this->timeout_seconds ?? $default ?? 10;
    }

    public function getMemoryLimitMb(?int $default = null): int
    {
        return $this->memory_limit_mb ?? $default ?? 256;
    }

    public function compareOutput(string $actualOutput): bool
    {
        return match ($this->comparison_strategy) {
            'exact' => trim($actualOutput) === trim($this->expected_output),
            'contains' => str_contains($actualOutput, $this->expected_output),
            'regex' => (bool)preg_match($this->expected_output, $actualOutput),
            default => trim($actualOutput) === trim($this->expected_output),
        };
    }
}