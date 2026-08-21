<?php

namespace App\Services\Research;

use App\Enums\EvidenceCategory;
use App\Enums\EvidenceConfidence;
use App\Enums\EvidenceQuality;
use App\Enums\ExecutionAnomaly;
use App\Enums\TaskRepetition;
use App\Models\Activity;
use App\Models\CodeExecution;
use App\Models\LearningEvent;
use App\Models\ValidatedEvidence;
use Illuminate\Support\Collection;

/**
 * Deterministic Evidence Validation layer.
 *
 * Raw LearningEvent → classification → context checks → quality → confidence → ValidatedEvidence.
 * Does not infer cognitive, affective, or psychological learner states.
 */
final class EvidenceValidationService
{
    public const NETWORK_ENVIRONMENT_UNKNOWN = 'unknown';

    /**
     * @var list<string>
     */
    private const PERFORMANCE_EVENTS = [
        'submission_accepted',
        'submission_rejected',
    ];

    /**
     * @var list<string>
     */
    private const INTERACTION_EVENTS = [
        'activity_started',
        'activity_completed',
        'code_run',
        'code_submit',
    ];

    /**
     * @var list<string>
     */
    private const EXECUTION_ANOMALY_STATUSES = [
        'timeout',
        'runtime_error',
        'system_error',
        'memory_limit',
        'resource_limit',
    ];

    /**
     * @return Collection<int, ValidatedEvidence>
     */
    public function validateEvent(LearningEvent $event): Collection
    {
        $execution = $this->relatedExecution($event);
        $context = $this->contextChecks($event, $execution);

        $records = collect();
        $records->push($this->persist($event, $this->primaryAssessment($event, $context, $execution), $context));

        $systemContext = $this->systemContextAssessment($event, $context, $execution);
        if ($systemContext !== null) {
            $records->push($this->persist($event, $systemContext, $context));
        }

        $behavioral = $this->behavioralAssessment($event, $context, $execution);
        if ($behavioral !== null) {
            $records->push($this->persist($event, $behavioral, $context));
        }

        return $records;
    }

    /**
     * @return array{task_repetition: TaskRepetition, task_difficulty: string, execution_anomaly: ExecutionAnomaly, network_environment: string}
     */
    private function contextChecks(LearningEvent $event, ?CodeExecution $execution): array
    {
        return [
            'task_repetition' => $this->taskRepetition($event),
            'task_difficulty' => $this->taskDifficulty($event),
            'execution_anomaly' => $this->executionAnomaly($event, $execution),
            'network_environment' => self::NETWORK_ENVIRONMENT_UNKNOWN,
        ];
    }

    private function taskRepetition(LearningEvent $event): TaskRepetition
    {
        if ($event->activity_id === null) {
            return TaskRepetition::Unknown;
        }

        $priorAttempts = $this->priorFormalAttemptCount($event);

        if ($priorAttempts === 0) {
            return TaskRepetition::New;
        }

        if ($this->completesTheOpenAttempt($event, $priorAttempts)) {
            return TaskRepetition::New;
        }

        return TaskRepetition::Repeated;
    }

    /**
     * activity_completed that immediately follows the first formal outcome belongs
     * to that same attempt. Later interaction after that outcome is a new exposure.
     */
    private function completesTheOpenAttempt(LearningEvent $event, int $priorAttempts): bool
    {
        if ($priorAttempts !== 1 || $this->canonicalEventType($event) !== 'activity_completed') {
            return false;
        }

        $preceding = LearningEvent::query()
            ->where('user_id', $event->user_id)
            ->where('activity_id', $event->activity_id)
            ->where('id', '<', $event->id)
            ->orderByDesc('id')
            ->first();

        if (! $preceding instanceof LearningEvent) {
            return false;
        }

        return in_array($this->canonicalEventType($preceding), self::PERFORMANCE_EVENTS, true);
    }

    /**
     * A formal attempt is a prior submission outcome for the same learner and activity.
     * Interaction events inside that first attempt (start, run, submit, first outcome,
     * complete immediately after that outcome) remain new. ActivityProgress emits
     * activity_started only once, so start count cannot represent a later exposure.
     */
    private function priorFormalAttemptCount(LearningEvent $event): int
    {
        $placeholders = implode(', ', array_fill(0, count(self::PERFORMANCE_EVENTS), '?'));

        return LearningEvent::query()
            ->where('user_id', $event->user_id)
            ->where('activity_id', $event->activity_id)
            ->whereRaw('LOWER(event_type) in ('.$placeholders.')', self::PERFORMANCE_EVENTS)
            ->where('id', '<', $event->id)
            ->count();
    }

    private function taskDifficulty(LearningEvent $event): string
    {
        if ($event->activity_id === null) {
            return 'unknown';
        }

        $activity = $event->relationLoaded('activity')
            ? $event->activity
            : Activity::query()->find($event->activity_id);

        $difficulty = $activity?->getDifficulty();

        return ($difficulty === null || $difficulty === '') ? 'unknown' : $difficulty;
    }

    private function executionAnomaly(LearningEvent $event, ?CodeExecution $execution): ExecutionAnomaly
    {
        if ($execution === null) {
            $status = $this->payload($event)['status'] ?? null;
            if (! is_string($status) || $status === '') {
                return ExecutionAnomaly::Unknown;
            }

            return in_array($status, self::EXECUTION_ANOMALY_STATUSES, true)
                ? ExecutionAnomaly::Detected
                : ExecutionAnomaly::None;
        }

        if ($execution->isTimeout()
            || $execution->isRuntimeError()
            || $execution->isMemoryLimit()
            || $execution->isResourceLimit()
            || $execution->status === 'system_error') {
            return ExecutionAnomaly::Detected;
        }

        return ExecutionAnomaly::None;
    }

    private function relatedExecution(LearningEvent $event): ?CodeExecution
    {
        $executionId = $this->payload($event)['execution_id'] ?? null;

        if (! is_numeric($executionId)) {
            return null;
        }

        return CodeExecution::query()->find((int) $executionId);
    }

    /**
     * @param  array{task_repetition: TaskRepetition, task_difficulty: string, execution_anomaly: ExecutionAnomaly, network_environment: string}  $context
     * @return array{category: EvidenceCategory, type: string, observed: array<string, mixed>, quality: EvidenceQuality, confidence: EvidenceConfidence, reason: string, source_type: ?string, source_id: ?int}
     */
    private function primaryAssessment(LearningEvent $event, array $context, ?CodeExecution $execution): array
    {
        $category = $this->primaryCategory($event, $context);
        $type = $this->primaryType($event, $context, $category, $execution);
        $observed = $this->observedValue($event, $type);
        [$quality, $confidence] = $this->qualityAndConfidence($category, $context);
        $reason = $this->validationReason($event, $category, $type, $observed['summary'], $context, $quality, $confidence);

        [$sourceType, $sourceId] = $this->sourceRecord($event, $execution);

        return [
            'category' => $category,
            'type' => $type,
            'observed' => $observed,
            'quality' => $quality,
            'confidence' => $confidence,
            'reason' => $reason,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ];
    }

    /**
     * @param  array{task_repetition: TaskRepetition, task_difficulty: string, execution_anomaly: ExecutionAnomaly, network_environment: string}  $context
     * @return array{category: EvidenceCategory, type: string, observed: array<string, mixed>, quality: EvidenceQuality, confidence: EvidenceConfidence, reason: string, source_type: ?string, source_id: ?int}|null
     */
    private function behavioralAssessment(LearningEvent $event, array $context, ?CodeExecution $execution): ?array
    {
        $type = $this->behavioralType($event);
        if ($type === null) {
            return null;
        }

        $category = EvidenceCategory::Behavioral;
        $observed = $this->observedValue($event, $type);
        [$quality, $confidence] = $this->qualityAndConfidence($category, $context);
        $reason = $this->validationReason($event, $category, $type, $observed['summary'], $context, $quality, $confidence);
        [$sourceType, $sourceId] = $this->sourceRecord($event, $execution);

        return [
            'category' => $category,
            'type' => $type,
            'observed' => $observed,
            'quality' => $quality,
            'confidence' => $confidence,
            'reason' => $reason,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ];
    }

    /**
     * @param  array{task_repetition: TaskRepetition, task_difficulty: string, execution_anomaly: ExecutionAnomaly, network_environment: string}  $context
     */
    private function primaryCategory(LearningEvent $event, array $context): EvidenceCategory
    {
        if (in_array($this->canonicalEventType($event), self::PERFORMANCE_EVENTS, true)) {
            return EvidenceCategory::Performance;
        }

        if ($context['execution_anomaly'] === ExecutionAnomaly::Detected) {
            return EvidenceCategory::SystemContext;
        }

        if (in_array($this->canonicalEventType($event), self::INTERACTION_EVENTS, true)) {
            return EvidenceCategory::Interaction;
        }

        return EvidenceCategory::SystemContext;
    }

    /**
     * @param  array{task_repetition: TaskRepetition, task_difficulty: string, execution_anomaly: ExecutionAnomaly, network_environment: string}  $context
     */
    private function primaryType(LearningEvent $event, array $context, EvidenceCategory $category, ?CodeExecution $execution): string
    {
        if ($category === EvidenceCategory::SystemContext && $context['execution_anomaly'] === ExecutionAnomaly::Detected) {
            return $this->anomalyEvidenceType($event, $execution);
        }

        return $this->canonicalEventType($event);
    }

    /**
     * Extra system/context record when a performance outcome also carries an execution anomaly.
     * Anomaly must not replace the observable performance evidence.
     *
     * @param  array{task_repetition: TaskRepetition, task_difficulty: string, execution_anomaly: ExecutionAnomaly, network_environment: string}  $context
     * @return array{category: EvidenceCategory, type: string, observed: array<string, mixed>, quality: EvidenceQuality, confidence: EvidenceConfidence, reason: string, source_type: ?string, source_id: ?int}|null
     */
    private function systemContextAssessment(LearningEvent $event, array $context, ?CodeExecution $execution): ?array
    {
        if ($context['execution_anomaly'] !== ExecutionAnomaly::Detected) {
            return null;
        }

        if (! in_array($this->canonicalEventType($event), self::PERFORMANCE_EVENTS, true)) {
            return null;
        }

        $category = EvidenceCategory::SystemContext;
        $type = $this->anomalyEvidenceType($event, $execution);
        $observed = $this->observedValue($event, $type);
        [$quality, $confidence] = $this->qualityAndConfidence($category, $context);
        $reason = $this->validationReason($event, $category, $type, $observed['summary'], $context, $quality, $confidence);
        [$sourceType, $sourceId] = $this->sourceRecord($event, $execution);

        return [
            'category' => $category,
            'type' => $type,
            'observed' => $observed,
            'quality' => $quality,
            'confidence' => $confidence,
            'reason' => $reason,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ];
    }

    private function anomalyEvidenceType(LearningEvent $event, ?CodeExecution $execution): string
    {
        $status = $execution?->status ?? ($this->payload($event)['status'] ?? null);

        return match ($status) {
            'timeout' => 'execution_timeout',
            'runtime_error' => 'execution_runtime_failure',
            default => 'execution_system_anomaly',
        };
    }

    private function canonicalEventType(LearningEvent $event): string
    {
        return strtolower($event->event_type);
    }

    private function behavioralType(LearningEvent $event): ?string
    {
        if ($event->activity_id === null) {
            return null;
        }

        $type = $this->canonicalEventType($event);

        if ($type === 'submission_rejected') {
            return $this->priorCanonicalEventCount($event, 'submission_rejected') >= 1
                ? 'repeated_submission_failures'
                : null;
        }

        if ($type === 'code_run') {
            return $this->priorCanonicalEventCount($event, 'code_run') >= 1
                ? 'repeated_execution'
                : null;
        }

        return null;
    }

    private function priorCanonicalEventCount(LearningEvent $event, string $canonicalType): int
    {
        return LearningEvent::query()
            ->where('user_id', $event->user_id)
            ->where('activity_id', $event->activity_id)
            ->whereRaw('LOWER(event_type) = ?', [$canonicalType])
            ->where('id', '<', $event->id)
            ->count();
    }

    /**
     * @return array{summary: string, event_type: string, result: mixed}
     */
    private function observedValue(LearningEvent $event, string $evidenceType): array
    {
        $summary = match ($evidenceType) {
            'submission_accepted' => 'Submission accepted',
            'submission_rejected' => 'Submission rejected',
            'repeated_submission_failures' => 'Repeated submission failures detected',
            'repeated_execution' => 'Repeated execution detected',
            'execution_timeout' => 'Execution timeout detected',
            'execution_runtime_failure' => 'Execution runtime failure detected',
            'execution_system_anomaly' => 'Execution system anomaly detected',
            'activity_started' => 'Activity started',
            'activity_completed' => 'Activity completed',
            'code_run' => 'Code run recorded',
            'code_submit' => 'Code submit recorded',
            default => 'Observable learning event recorded',
        };

        $result = [
            'event_type' => $event->event_type,
            'status' => $this->payload($event)['status'] ?? null,
            'passes_evaluation' => $this->payload($event)['passes_evaluation'] ?? null,
        ];

        return [
            'summary' => $summary,
            'event_type' => $event->event_type,
            'result' => $result,
        ];
    }

    /**
     * @param  array{task_repetition: TaskRepetition, task_difficulty: string, execution_anomaly: ExecutionAnomaly, network_environment: string}  $context
     * @return array{0: EvidenceQuality, 1: EvidenceConfidence}
     */
    private function qualityAndConfidence(EvidenceCategory $category, array $context): array
    {
        if ($context['execution_anomaly'] === ExecutionAnomaly::Detected) {
            return [EvidenceQuality::Uncertain, EvidenceConfidence::Low];
        }

        if ($context['task_repetition'] === TaskRepetition::Unknown) {
            return [EvidenceQuality::Uncertain, EvidenceConfidence::Low];
        }

        if ($category === EvidenceCategory::Performance && $context['task_repetition'] === TaskRepetition::Repeated) {
            return [EvidenceQuality::ContextDependent, EvidenceConfidence::Medium];
        }

        if ($category === EvidenceCategory::Behavioral) {
            return [EvidenceQuality::ContextDependent, EvidenceConfidence::Medium];
        }

        $quality = EvidenceQuality::Valid;
        $confidence = ($context['task_repetition'] === TaskRepetition::New
            && $context['execution_anomaly'] === ExecutionAnomaly::None
            && $context['task_difficulty'] !== 'unknown')
            ? EvidenceConfidence::High
            : EvidenceConfidence::Medium;

        return [$quality, $confidence];
    }

    /**
     * @param  array{task_repetition: TaskRepetition, task_difficulty: string, execution_anomaly: ExecutionAnomaly, network_environment: string}  $context
     */
    private function validationReason(
        LearningEvent $event,
        EvidenceCategory $category,
        string $evidenceType,
        string $observedSummary,
        array $context,
        EvidenceQuality $quality,
        EvidenceConfidence $confidence,
    ): string {
        $parts = [
            'Observed: '.$event->event_type.'.',
            $observedSummary.'.',
            'Task exposure is '.$context['task_repetition']->value.'.',
            'Task difficulty: '.$context['task_difficulty'].'.',
            'Execution anomaly: '.$context['execution_anomaly']->value.'.',
            'Network/environment telemetry is '.$context['network_environment'].'.',
        ];

        if ($quality === EvidenceQuality::ContextDependent) {
            $parts[] = 'Quality is context_dependent because repeated exposure to the task requires contextual interpretation before inferring learner state.';
        } elseif ($quality === EvidenceQuality::Uncertain && $context['execution_anomaly'] === ExecutionAnomaly::Detected) {
            $parts[] = 'Quality is uncertain because an observable execution anomaly affects interpretation of this evidence.';
        } elseif ($quality === EvidenceQuality::Uncertain) {
            $parts[] = 'Quality is uncertain because required task or execution context is unavailable.';
        } else {
            $parts[] = 'Quality is valid: the observation is usable as evidence without assuming a learner learning state.';
        }

        $parts[] = 'Confidence is '.$confidence->value.' for evidence validity/usefulness, not for a psychological or learning state.';
        $parts[] = 'Category is '.$category->value.' ('.$evidenceType.').';

        return implode(' ', $parts);
    }

    /**
     * @param  array{category: EvidenceCategory, type: string, observed: array<string, mixed>, quality: EvidenceQuality, confidence: EvidenceConfidence, reason: string, source_type: ?string, source_id: ?int}  $assessment
     * @param  array{task_repetition: TaskRepetition, task_difficulty: string, execution_anomaly: ExecutionAnomaly, network_environment: string}  $context
     */
    private function persist(LearningEvent $event, array $assessment, array $context): ValidatedEvidence
    {
        return ValidatedEvidence::query()->updateOrCreate(
            [
                'learning_event_id' => $event->id,
                'evidence_category' => $assessment['category']->value,
                'evidence_type' => $assessment['type'],
            ],
            [
                'user_id' => $event->user_id,
                'activity_id' => $event->activity_id,
                'source_record_type' => $assessment['source_type'],
                'source_record_id' => $assessment['source_id'],
                'observed_value' => $assessment['observed'],
                'context_summary' => [
                    'task_repetition' => $context['task_repetition']->value,
                    'task_difficulty' => $context['task_difficulty'],
                    'execution_anomaly' => $context['execution_anomaly']->value,
                    'network_environment' => $context['network_environment'],
                ],
                'quality' => $assessment['quality']->value,
                'confidence' => $assessment['confidence']->value,
                'validation_reason' => $assessment['reason'],
                'validated_at' => now(),
            ],
        );
    }

    /**
     * Submission outcomes prefer ActivitySubmission when present so evidence
     * does not depend on a later payload mutation.
     *
     * @return array{0: ?string, 1: ?int}
     */
    private function sourceRecord(LearningEvent $event, ?CodeExecution $execution): array
    {
        $submissionId = $this->submissionSourceId($event);
        if ($submissionId !== null) {
            return ['activity_submission', $submissionId];
        }

        if ($execution instanceof CodeExecution) {
            return ['code_execution', $execution->id];
        }

        return [null, null];
    }

    private function submissionSourceId(LearningEvent $event): ?int
    {
        $submissionId = $this->payload($event)['submission_id'] ?? null;

        return is_numeric($submissionId) ? (int) $submissionId : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(LearningEvent $event): array
    {
        return is_array($event->payload) ? $event->payload : [];
    }
}
