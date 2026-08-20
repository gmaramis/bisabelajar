<?php

namespace App\Support;

use App\Enums\ActivityType;
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
    ];

    /**
     * @return list<string>
     */
    public static function studentKeys(ActivityType $type): array
    {
        return match ($type) {
            ActivityType::Lesson, ActivityType::Assignment, ActivityType::Project => ['instructions'],
            ActivityType::Quiz, ActivityType::Exam => ['instructions', 'max_attempts', 'time_limit_minutes'],
            ActivityType::CodingExercise => ['instructions', 'language'],
            ActivityType::Discussion => ['instructions', 'prompt'],
        };
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
            ActivityType::CodingExercise => ['notes', 'expected_output'],
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
            'configuration.tutor' => ['nullable', 'array'],
            'configuration.tutor.notes' => ['nullable', 'string', 'max:10000'],
            'configuration.tutor.answer_key' => ['nullable', 'string', 'max:10000'],
            'configuration.tutor.rubric' => ['nullable', 'string', 'max:10000'],
            'configuration.tutor.expected_output' => ['nullable', 'string', 'max:10000'],
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
