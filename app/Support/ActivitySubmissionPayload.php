<?php

namespace App\Support;

use App\Enums\ActivityType;
use App\Models\Activity;
use Illuminate\Validation\Validator;

final class ActivitySubmissionPayload
{
    /**
     * @var list<string>
     */
    public const FORBIDDEN_KEYS = [
        'scoring',
        'score',
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
    public static function allowedKeys(ActivityType $type): array
    {
        return match ($type) {
            ActivityType::Quiz, ActivityType::Exam => ['body', 'answers'],
            ActivityType::CodingExercise => ['body', 'code'],
            default => ['body'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'payload' => ['required', 'array'],
            'payload.body' => ['required', 'string', 'max:50000'],
            'payload.answers' => ['nullable', 'array', 'max:50'],
            'payload.answers.*' => ['string', 'max:5000'],
            'payload.code' => ['nullable', 'string', 'max:50000'],
        ];
    }

    public static function validateForActivity(Validator $validator, Activity $activity): void
    {
        /** @var array<string, mixed> $payload */
        $payload = $validator->getData()['payload'] ?? [];

        if (! is_array($payload)) {
            return;
        }

        $unknown = array_values(array_diff(array_keys($payload), self::allowedKeys($activity->type)));
        if ($unknown !== []) {
            $validator->errors()->add('payload', 'Unknown submission keys are not allowed for this activity type.');
        }

        if (self::containsForbiddenKeys($payload)) {
            $validator->errors()->add('payload', 'Scoring, grading, NEXUS, mastery, and execution keys are not allowed.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function prune(array $payload): array
    {
        $pruned = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $value = array_values(array_filter($value, fn ($item) => $item !== null && $item !== ''));
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
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function normalize(ActivityType $type, array $payload): array
    {
        $normalized = [
            'body' => trim((string) $payload['body']),
        ];

        foreach (self::allowedKeys($type) as $key) {
            if ($key === 'body' || ! array_key_exists($key, $payload) || $payload[$key] === null || $payload[$key] === '') {
                continue;
            }

            $normalized[$key] = $key === 'answers'
                ? array_values(array_map(fn ($item) => trim((string) $item), $payload[$key]))
                : trim((string) $payload[$key]);
        }

        return $normalized;
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
