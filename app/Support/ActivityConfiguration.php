<?php

namespace App\Support;

use App\Enums\ActivityType;
use App\Enums\CompletionRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ActivityConfiguration
{
    /**
     * @var list<string>
     */
    public const FORBIDDEN_KEYS = [
        'scoring',
        'grade',
        'grading',
        'nexus',
        'mastery',
        'recommendation',
        'code_execution',
        'sandbox',
        'language_execution_profile', // M3: handled separately
        'starter_code', // M3: handled in ProgrammingActivity
        'test_cases', // M3: handled separately
    ];

    /**
     * @return list<string>
     */
    public static function studentKeys(ActivityType $type): array
    {
        $keys = match ($type) {
            ActivityType::Lesson, ActivityType::Assignment, ActivityType::Project => ['instructions'],
            ActivityType::Quiz, ActivityType::Exam => ['instructions', 'max_attempts', 'time_limit_minutes'],
            ActivityType::CodingExercise => ['instructions', 'language', 'language_execution_profile_id', 'starter_code', 'editable_files', 'execution_time_limit_seconds', 'memory_limit_mb'],
            ActivityType::Discussion => ['instructions', 'prompt'],
        };

        $keys[] = 'completion_rule';

        return $keys;
    }

    /**
     * @return list<string>
     */
    public static function tutorKeys(ActivityType $type): array
    {
        return match ($type) {
            ActivityType::Lesson, ActivityType::Discussion => ['notes'],
            ActivityType::Quiz, ActivityType::Exam => ['notes', 'answer_key'],
            ActivityType::Assignment, ActivityType::Project => ['notes', 'rubric'],
            ActivityType::CodingExercise => ['notes', 'expected_output', 'test_cases', 'evaluation_config'],
        };
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array<string, mixed>
     */
    public static function prune(array $configuration): array
    {
        $pruned = [];

        foreach ($configuration as $key => $value) {
            if (is_array($value)) {
                $value = self::prune($value);
                if ($value === []) {
                    continue;
                }

                $pruned[$key] = $value;

                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $pruned[$key] = $value;
        }

        return $pruned;
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'configuration' => ['required', 'array'],
            'configuration.instructions' => ['required', 'string', 'max:10000'],
            'configuration.prompt' => ['nullable', 'string', 'max:10000'],
            'configuration.max_attempts' => ['nullable', 'integer', 'min:1', 'max:20'],
            'configuration.time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'configuration.language' => ['nullable', 'string', 'max:32'],
            'configuration.language_execution_profile_id' => ['nullable', 'integer', 'exists:language_execution_profiles,id'],
            'configuration.starter_code' => ['nullable', 'string', 'max:100000'],
            'configuration.editable_files' => ['nullable', 'array'],
            'configuration.execution_time_limit_seconds' => ['nullable', 'integer', 'min:1', 'max:300'],
            'configuration.memory_limit_mb' => ['nullable', 'integer', 'min:64', 'max:2048'],
            'configuration.completion_rule' => ['nullable', 'string', Rule::enum(CompletionRule::class)],
            'configuration.tutor' => ['nullable', 'array'],
            'configuration.tutor.notes' => ['nullable', 'string', 'max:10000'],
            'configuration.tutor.answer_key' => ['nullable', 'string', 'max:10000'],
            'configuration.tutor.rubric' => ['nullable', 'string', 'max:10000'],
            'configuration.tutor.expected_output' => ['nullable', 'string', 'max:10000'],
            'configuration.tutor.test_cases' => ['nullable', 'array'],
            'configuration.tutor.evaluation_config' => ['nullable', 'array'],
            'configuration.extensions' => ['nullable', 'array'],
        ];
    }

    public static function validateForType(Validator $validator, ActivityType $type): void
    {
        /** @var array<string, mixed> $configuration */
        $configuration = $validator->getData()['configuration'] ?? [];

        if (! is_array($configuration)) {
            return;
        }

        $allowed = array_merge(self::studentKeys($type), ['tutor', 'extensions']);
        $unknown = array_values(array_diff(array_keys($configuration), $allowed));

        if ($unknown !== []) {
            $validator->errors()->add('configuration', 'Unknown configuration keys are not allowed for this activity type.');
        }

        if (self::containsForbiddenKeys($configuration)) {
            $validator->errors()->add('configuration', 'Scoring, grading, NEXUS, mastery, and execution keys are not allowed.');
        }

        if ($type === ActivityType::Discussion && ! filled($configuration['prompt'] ?? null)) {
            $validator->errors()->add('configuration.prompt', 'A discussion prompt is required.');
        }

        // M3: Validate CodingExercise specific fields
        if ($type === ActivityType::CodingExercise) {
            if (isset($configuration['language_execution_profile_id']) && ! is_int($configuration['language_execution_profile_id'])) {
                $validator->errors()->add('configuration.language_execution_profile_id', 'Language execution profile ID must be an integer.');
            }
            if (isset($configuration['execution_time_limit_seconds']) && ($configuration['execution_time_limit_seconds'] < 1 || $configuration['execution_time_limit_seconds'] > 300)) {
                $validator->errors()->add('configuration.execution_time_limit_seconds', 'Execution time limit must be between 1 and 300 seconds.');
            }
            if (isset($configuration['memory_limit_mb']) && ($configuration['memory_limit_mb'] < 64 || $configuration['memory_limit_mb'] > 2048)) {
                $validator->errors()->add('configuration.memory_limit_mb', 'Memory limit must be between 64 and 2048 MB.');
            }
        }

        $tutor = $configuration['tutor'] ?? [];
        if ($tutor !== [] && ! is_array($tutor)) {
            $validator->errors()->add('configuration.tutor', 'Tutor configuration must be an object.');

            return;
        }

        if (is_array($tutor)) {
            $unknownTutor = array_values(array_diff(array_keys($tutor), self::tutorKeys($type)));
            if ($unknownTutor !== []) {
                $validator->errors()->add('configuration.tutor', 'Unknown tutor configuration keys are not allowed for this activity type.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array<string, mixed>
     */
    public static function normalize(ActivityType $type, array $configuration): array
    {
        $normalized = [
            'instructions' => trim((string) $configuration['instructions']),
        ];

        foreach (self::studentKeys($type) as $key) {
            if ($key === 'instructions' || ! array_key_exists($key, $configuration) || $configuration[$key] === null || $configuration[$key] === '') {
                continue;
            }

            $normalized[$key] = in_array($key, ['max_attempts', 'time_limit_minutes'], true)
                ? (int) $configuration[$key]
                : trim((string) $configuration[$key]);
        }

        $tutor = [];
        foreach (self::tutorKeys($type) as $key) {
            $value = $configuration['tutor'][$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $tutor[$key] = trim((string) $value);
        }

        if ($tutor !== []) {
            $normalized['tutor'] = $tutor;
        }

        $extensions = $configuration['extensions'] ?? null;
        if (is_array($extensions) && $extensions !== []) {
            $normalized['extensions'] = $extensions;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $configuration
     * @return array<string, mixed>
     */
    public static function studentSafe(ActivityType $type, ?array $configuration): array
    {
        $configuration ??= [];
        $safe = [];

        foreach (self::studentKeys($type) as $key) {
            if (! array_key_exists($key, $configuration) || $configuration[$key] === null || $configuration[$key] === '') {
                continue;
            }

            $safe[$key] = $configuration[$key];
        }

        return $safe;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private static function containsForbiddenKeys(array $value): bool
    {
        foreach ($value as $key => $child) {
            if (in_array((string) $key, self::FORBIDDEN_KEYS, true)) {
                return true;
            }

            if (is_array($child) && self::containsForbiddenKeys($child)) {
                return true;
            }
        }

        return false;
    }
}
