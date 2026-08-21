<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'identifier',
    'display_name',
    'file_extension',
    'source_filename',
    'docker_image',
    'compile_command',
    'run_command',
    'execution_mode',
    'timeout_seconds',
    'memory_limit_mb',
    'cpu_limit',
    'network_enabled',
    'enabled',
    'environment_variables',
    'allowed_files',
])]
class LanguageExecutionProfile extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'execution_mode' => 'string',
            'timeout_seconds' => 'integer',
            'memory_limit_mb' => 'integer',
            'cpu_limit' => 'integer',
            'network_enabled' => 'boolean',
            'enabled' => 'boolean',
            'environment_variables' => 'array',
            'allowed_files' => 'array',
        ];
    }

    public function programmingActivities(): HasMany
    {
        return $this->hasMany(ProgrammingActivity::class);
    }

    public function codeExecutions(): HasMany
    {
        return $this->hasMany(CodeExecution::class);
    }

    public function testCases(): HasMany
    {
        return $this->hasMany(TestCase::class);
    }

    public function isCompiled(): bool
    {
        return $this->execution_mode === 'compiled';
    }

    public function isInterpreted(): bool
    {
        return $this->execution_mode === 'interpreted';
    }

    public function defaultSourceFilename(): string
    {
        return $this->source_filename;
    }

    public function defaultFileExtension(): string
    {
        return $this->file_extension;
    }
}