<?php

namespace App\Services\Evaluation\Explainability;

use App\Enums\ActivityType;
use App\Enums\BloomLevel;
use App\Enums\ContextDimension;
use App\Enums\DaveLevel;
use App\Enums\EvidenceCategory;
use App\Enums\EvidenceConfidence;
use App\Enums\EvidenceQuality;
use App\Enums\InterventionType;
use App\Enums\LearningStateValue;
use App\Enums\StateConfidence;
use App\Models\Activity;
use App\Models\AdaptiveIntervention;
use App\Models\Course;
use App\Models\LearningEvent;
use App\Models\LearningState;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\User;
use App\Models\ValidatedEvidence;
use App\Services\Evaluation\EvaluationStatus;
use App\Services\Evaluation\LearningState\EvidenceSpec;
use App\Services\Research\AdaptiveInterventionService;
use App\Services\Research\AiAssistedReassessmentService;
use App\Services\Research\ContextualVariationQuery;
use App\Services\Research\InterventionResponseQuery;
use App\Services\Research\LearningStateInferenceService;
use App\Services\Research\NextLearningActionService;
use App\Services\Research\WeakAreaIdentificationQuery;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Executes the real authoritative NEXUS components against synthetic scenarios and
 * captures their explanation surfaces for explainability/trustworthiness validation
 * without persisting anything (M6-05).
 *
 * READ-ONLY / SOURCE-OF-TRUTH PROTECTION: fixtures and every component write happen
 * inside a transaction that is ALWAYS rolled back; the normalized explanation is
 * captured (privacy-safe, timestamp-free) before rollback. The runner calls only
 * authoritative component entry points and never mutates existing records or rules.
 */
final class ExplainabilityEvaluationRunner
{
    public function __construct(
        private readonly LearningStateInferenceService $inference,
        private readonly AdaptiveInterventionService $interventionService,
        private readonly NextLearningActionService $nextActionService,
        private readonly WeakAreaIdentificationQuery $weakAreaQuery,
        private readonly AiAssistedReassessmentService $reassessmentService,
        private readonly InterventionResponseQuery $responseQuery,
        private readonly ContextualVariationQuery $contextualVariationQuery,
        private readonly ExplanationComparator $comparator,
        private readonly ExplainabilityConstraintChecker $constraintChecker,
    ) {}

    /**
     * @param  list<ExplainabilityScenario>  $scenarios
     * @return list<ExplainabilityValidationResult>
     */
    public function runMany(array $scenarios): array
    {
        return array_map(fn (ExplainabilityScenario $s): ExplainabilityValidationResult => $this->run($s), $scenarios);
    }

    public function run(ExplainabilityScenario $scenario): ExplainabilityValidationResult
    {
        $notes = [];
        $failureHandled = false;

        try {
            $actual = $this->captureActual($scenario);
        } catch (Throwable $e) {
            $failureHandled = true;
            $actual = new ActualExplanation(
                learnerRef: $this->learnerRef($scenario->scenarioId),
                component: $scenario->component,
                explanationText: '',
                rule: null,
                hasProvenance: false,
                provenanceIds: [],
                confidenceVisible: false,
                confidenceValue: null,
                bloomDaveTaskDemand: false,
                claimsCausal: false,
                deterministic: false,
            );
            $notes[] = 'scenario execution raised an exception and was contained: '.$e->getMessage();
        }

        $comparison = $this->comparator->compare($scenario->expected, $actual);
        $constraintCheck = $this->constraintChecker->check($scenario->expected, $actual);
        $provenanceCheck = [
            'scenario_id' => $scenario->scenarioId,
            'component' => $scenario->component,
            'has_provenance' => $actual->hasProvenance,
            'provenance_id_count' => count($actual->provenanceIds),
            'traceable' => $scenario->expected->requireProvenance ? $actual->hasProvenance : true,
        ];

        $status = $comparison['status'];
        $dimensions = $comparison['dimensions'];
        $differences = $comparison['differences'];

        $dimensions['constraint_compliance'] = $constraintCheck['compliant'] ? 'pass' : 'fail';
        $dimensions['privacy'] = ($constraintCheck['checks']['privacy_safe'] ?? false) ? 'pass' : 'fail';
        $dimensions['determinism'] = $actual->deterministic ? 'pass' : 'fail';
        $dimensions['failure_transparency'] = $failureHandled ? 'review' : 'pass';
        $dimensions['boundary_clarity'] = trim($actual->explanationText) !== '' ? 'pass' : ($scenario->expected->requireReason ? 'fail' : 'pass');

        if (! $constraintCheck['compliant']) {
            $differences = array_merge($differences, array_map(fn (string $v): string => 'constraint: '.$v, $constraintCheck['violations']));
            $status = EvaluationStatus::Fail;
        }

        if (! $actual->deterministic) {
            $differences[] = 'determinism: repeated execution produced a different explanation/rule';
            $status = EvaluationStatus::Fail;
        }

        if ($failureHandled && $status === EvaluationStatus::Pass) {
            $status = EvaluationStatus::Review;
        }

        return new ExplainabilityValidationResult(
            scenarioId: $scenario->scenarioId,
            component: $scenario->component,
            status: $status,
            expected: $scenario->expected,
            actual: $actual,
            differences: array_values($differences),
            dimensions: $dimensions,
            provenanceCheck: $provenanceCheck,
            constraintCheck: $constraintCheck,
            notes: $notes,
        );
    }

    private function captureActual(ExplainabilityScenario $scenario): ActualExplanation
    {
        DB::beginTransaction();

        try {
            return match ($scenario->component) {
                'learning_state' => $this->captureLearningState($scenario),
                'intervention' => $this->captureIntervention($scenario),
                'next_action' => $this->captureNextAction($scenario),
                'weak_area' => $this->captureWeakArea($scenario),
                'reassessment' => $this->captureReassessment($scenario),
                'response' => $this->captureResponse($scenario),
                'contextual_variation' => $this->captureContextualVariation($scenario),
                default => throw new \InvalidArgumentException('Unknown component: '.$scenario->component),
            };
        } finally {
            DB::rollBack();
        }
    }

    private function captureLearningState(ExplainabilityScenario $scenario): ActualExplanation
    {
        [$student, $course, $activity] = $this->fixtures($scenario->concept);
        foreach ($scenario->evidence as $spec) {
            $this->seedEvidence($student->id, $course->id, $activity->id, $spec);
        }

        $first = $this->inference->inferForLearnerActivity($student->id, $activity->id);
        $second = $this->inference->inferForLearnerActivity($student->id, $activity->id);

        $deterministic = $first->explanation === $second->explanation && $first->inference_rule === $second->inference_rule;
        $taskDemand = $this->taskDemand($first->bloom_demand?->value, $first->cognitive_indicator, $first->dave_demand?->value, $first->psychomotor_indicator);

        return new ActualExplanation(
            learnerRef: $this->learnerRef($scenario->scenarioId),
            component: 'learning_state',
            explanationText: (string) $first->explanation,
            rule: $first->inference_rule,
            hasProvenance: $first->validatedEvidence->isNotEmpty(),
            provenanceIds: $first->validatedEvidence->pluck('id')->sort()->values()->all(),
            confidenceVisible: true,
            confidenceValue: $first->state_confidence->value,
            bloomDaveTaskDemand: $taskDemand,
            claimsCausal: false,
            deterministic: $deterministic,
        );
    }

    private function captureIntervention(ExplainabilityScenario $scenario): ActualExplanation
    {
        [$student, $course, $activity] = $this->fixtures($scenario->concept);
        foreach ($scenario->evidence as $spec) {
            $this->seedEvidence($student->id, $course->id, $activity->id, $spec);
        }

        $state = $this->inference->inferForLearnerActivity($student->id, $activity->id);
        $first = $this->interventionService->createForLearningState($state);
        $second = $this->interventionService->createForLearningState($state->fresh(['validatedEvidence', 'activity']));

        $deterministic = $first->reason === $second->reason && $first->selection_rule === $second->selection_rule;
        $metadata = is_array($first->metadata) ? $first->metadata : [];

        return new ActualExplanation(
            learnerRef: $this->learnerRef($scenario->scenarioId),
            component: 'intervention',
            explanationText: (string) $first->reason,
            rule: $first->selection_rule,
            hasProvenance: $first->learning_state_id !== null,
            provenanceIds: array_values(array_map('intval', $metadata['validated_evidence_ids'] ?? [])),
            confidenceVisible: isset($metadata['state_confidence']),
            confidenceValue: $metadata['state_confidence'] ?? null,
            bloomDaveTaskDemand: true,
            claimsCausal: (bool) ($metadata['longitudinal_analysis'] ?? false),
            deterministic: $deterministic,
        );
    }

    private function captureNextAction(ExplainabilityScenario $scenario): ActualExplanation
    {
        [$student, $course, $activity] = $this->fixtures($scenario->concept);
        foreach ($scenario->evidence as $spec) {
            $this->seedEvidence($student->id, $course->id, $activity->id, $spec);
        }

        $state = $this->inference->inferForLearnerActivity($student->id, $activity->id);
        $intervention = $this->interventionService->createForLearningState($state);
        $first = $this->nextActionService->decideForLearningState($state, $intervention, null);
        $second = $this->nextActionService->decideForLearningState($state, $intervention, null);

        $deterministic = $first->reason === $second->reason && $first->decision_rule === $second->decision_rule;
        $metadata = is_array($first->metadata) ? $first->metadata : [];

        return new ActualExplanation(
            learnerRef: $this->learnerRef($scenario->scenarioId),
            component: 'next_action',
            explanationText: (string) $first->reason,
            rule: $first->decision_rule,
            hasProvenance: $first->learning_state_id !== null,
            provenanceIds: array_filter([$first->learning_state_id, $first->adaptive_intervention_id]),
            confidenceVisible: isset($metadata['state_confidence']),
            confidenceValue: $metadata['state_confidence'] ?? null,
            bloomDaveTaskDemand: true,
            claimsCausal: (bool) ($metadata['ml_decision'] ?? false) || (bool) ($metadata['llm_decision'] ?? false),
            deterministic: $deterministic,
        );
    }

    private function captureWeakArea(ExplainabilityScenario $scenario): ActualExplanation
    {
        [$student, $course, $activity] = $this->fixtures($scenario->concept, sentinel: true);
        $this->seedPersistentWeakArea($student->id, $course->id, $activity->id);

        $first = $this->firstFinding($this->weakAreaQuery->forLearnerCourse($student->id, $course->id));
        $second = $this->firstFinding($this->weakAreaQuery->forLearnerCourse($student->id, $course->id));

        $deterministic = ($first['explanation'] ?? null) === ($second['explanation'] ?? null)
            && ($first['detection_rule'] ?? null) === ($second['detection_rule'] ?? null);

        return new ActualExplanation(
            learnerRef: $this->learnerRef($scenario->scenarioId),
            component: 'weak_area',
            explanationText: (string) ($first['explanation'] ?? ''),
            rule: $first['detection_rule'] ?? null,
            hasProvenance: ! empty($first['supporting_evidence_ids']) && ! empty($first['supporting_learning_state_ids']),
            provenanceIds: array_values(array_map('intval', $first['supporting_evidence_ids'] ?? [])),
            confidenceVisible: isset($first['evidence_confidence_summary']),
            confidenceValue: null,
            bloomDaveTaskDemand: ($first['bloom_semantics'] ?? null) === 'task_demand',
            claimsCausal: (bool) ($first['claims_causal_improvement'] ?? false),
            deterministic: $deterministic,
        );
    }

    private function captureReassessment(ExplainabilityScenario $scenario): ActualExplanation
    {
        [$student, $course, $activity] = $this->fixtures($scenario->concept, sentinel: true);
        $this->seedPersistentWeakArea($student->id, $course->id, $activity->id);

        $first = $this->reassessmentService->createCandidateForLearningArea($student->id, $course->id, 'concept:'.$scenario->concept);
        $second = $this->reassessmentService->createCandidateForLearningArea($student->id, $course->id, 'concept:'.$scenario->concept);

        $spec = is_array($first['specification'] ?? null) ? $first['specification'] : [];
        $provenance = is_array($first['provenance'] ?? null) ? $first['provenance'] : [];

        $deterministic = ($first['status'] ?? null) === ($second['status'] ?? null)
            && ($spec['concept'] ?? null) === ($second['specification']['concept'] ?? null);

        return new ActualExplanation(
            learnerRef: $this->learnerRef($scenario->scenarioId),
            component: 'reassessment',
            explanationText: (string) ($spec['learning_objective'] ?? ''),
            rule: null,
            hasProvenance: ! empty($provenance['learning_state_ids']) && ! empty($provenance['validated_evidence_ids']),
            provenanceIds: array_values(array_map('intval', $provenance['validated_evidence_ids'] ?? [])),
            confidenceVisible: false,
            confidenceValue: null,
            bloomDaveTaskDemand: ($spec['bloom_semantics'] ?? null) === 'task_demand',
            claimsCausal: (bool) ($first['claims_effectiveness'] ?? false) || (bool) ($first['claims_improvement'] ?? false),
            deterministic: $deterministic,
        );
    }

    private function captureResponse(ExplainabilityScenario $scenario): ActualExplanation
    {
        [$student, $course, $activity] = $this->fixtures($scenario->concept);

        $before = $this->makeState($student->id, $activity->id, LearningStateValue::NeedsSupport, now()->subMinutes(40), 'submission_rejected', 'unresolved_performance_outcome_observed', $course->id);
        $intervention = $this->makeIntervention($student->id, $activity->id, $before, now()->subMinutes(30));
        $this->makeState($student->id, $activity->id, LearningStateValue::Progressing, now()->subMinutes(10), 'submission_accepted', 'successful_task_outcome_observed', $course->id);

        $first = $this->responseQuery->forIntervention($intervention->fresh(['learningState', 'activity', 'nextLearningActions']));
        $second = $this->responseQuery->forIntervention($intervention->fresh(['learningState', 'activity', 'nextLearningActions']));

        $interp = $first['research_interpretation'];
        $provenance = $first['provenance'];

        $deterministic = $interp['explanation'] === $second['research_interpretation']['explanation']
            && $interp['comparison_rule'] === $second['research_interpretation']['comparison_rule'];

        return new ActualExplanation(
            learnerRef: $this->learnerRef($scenario->scenarioId),
            component: 'response',
            explanationText: (string) $interp['explanation'],
            rule: $interp['comparison_rule'],
            hasProvenance: ($provenance['adaptive_intervention_id'] ?? null) !== null,
            provenanceIds: array_values(array_map('intval', $provenance['post_validated_evidence_ids'] ?? [])),
            confidenceVisible: isset($first['confidence']),
            confidenceValue: $first['confidence'] ?? null,
            bloomDaveTaskDemand: ($first['learning_area']['bloom_semantics'] ?? null) === 'task_demand',
            claimsCausal: (bool) ($interp['claims_intervention_caused_improvement'] ?? false) || (bool) ($interp['claims_causal_effectiveness'] ?? false),
            deterministic: $deterministic,
        );
    }

    private function captureContextualVariation(ExplainabilityScenario $scenario): ActualExplanation
    {
        [$student, $course, $activity] = $this->fixtures($scenario->concept);
        $this->seedPersistentWeakArea($student->id, $course->id, $activity->id);

        $first = $this->contextualVariationQuery->forCourse($course->id, ContextDimension::BloomTaskDemand);
        $second = $this->contextualVariationQuery->forCourse($course->id, ContextDimension::BloomTaskDemand);

        $summary = is_array($first['variation_summary'] ?? null) ? $first['variation_summary'] : [];
        $contexts = is_array($first['contexts'] ?? null) ? $first['contexts'] : [];

        $deterministic = ($summary['explanation'] ?? null) === ($second['variation_summary']['explanation'] ?? null);

        return new ActualExplanation(
            learnerRef: $this->learnerRef($scenario->scenarioId),
            component: 'contextual_variation',
            explanationText: (string) ($summary['explanation'] ?? ''),
            rule: null,
            hasProvenance: $contexts !== [],
            provenanceIds: [count($contexts)],
            confidenceVisible: false,
            confidenceValue: null,
            bloomDaveTaskDemand: true,
            claimsCausal: (bool) ($summary['claims_context_caused_outcome'] ?? false),
            deterministic: $deterministic,
        );
    }

    // ----- fixtures & helpers -------------------------------------------------

    /**
     * @return array{0: User, 1: Course, 2: Activity}
     */
    private function fixtures(string $concept, bool $sentinel = false): array
    {
        $tutor = User::factory()->tutor()->create();
        $student = User::factory()->student()->create($sentinel ? [
            'name' => 'Secret Learner Sentinel',
            'email' => 'sentinel.learner@example.com',
        ] : []);
        $course = Course::factory()->for($tutor, 'owner')->published()->public()->create();
        $module = Module::factory()->for($course)->published()->create();
        $unit = LearningUnit::factory()->for($module)->published()->create();
        $activity = Activity::factory()->for($unit, 'learningUnit')->published()->type(ActivityType::CodingExercise)->create([
            'concept' => $concept,
            'learning_objective' => 'Write a loop that iterates a list.',
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
            'difficulty' => 'medium',
        ]);

        return [$student, $course, $activity];
    }

    private function seedEvidence(int $userId, int $courseId, int $activityId, EvidenceSpec $spec): void
    {
        $event = LearningEvent::query()->create([
            'user_id' => $userId,
            'course_id' => $courseId,
            'activity_id' => $activityId,
            'event_type' => match ($spec->evidenceType) {
                'repeated_submission_failures' => 'submission_rejected',
                'repeated_execution', 'execution_runtime_failure' => 'code_run',
                default => $spec->evidenceType,
            },
            'payload' => ['synthetic' => true],
            'occurred_at' => now(),
        ]);

        ValidatedEvidence::query()->create([
            'user_id' => $userId,
            'activity_id' => $activityId,
            'learning_event_id' => $event->id,
            'evidence_category' => $spec->category->value,
            'evidence_type' => $spec->evidenceType,
            'observed_value' => ['summary' => $spec->evidenceType],
            'context_summary' => ['task_repetition' => 'new', 'task_difficulty' => 'medium', 'execution_anomaly' => 'none', 'network_environment' => 'unknown'],
            'quality' => $spec->quality->value,
            'confidence' => $spec->confidence->value,
            'validation_reason' => 'Synthetic validated evidence for M6-05 evaluation.',
            'validated_at' => now(),
        ]);
    }

    private function seedPersistentWeakArea(int $userId, int $courseId, int $activityId): void
    {
        foreach ([40, 30, 20] as $minutes) {
            $state = LearningState::factory()->create([
                'user_id' => $userId,
                'activity_id' => $activityId,
                'state' => LearningStateValue::NeedsSupport,
                'state_confidence' => StateConfidence::Medium,
                'inferred_at' => now()->subMinutes($minutes),
                'inference_key' => hash('sha256', uniqid('m6-05-persist-'.$minutes, true)),
                'cognitive_indicator' => 'unresolved_performance_outcome_observed',
                'behavioral_indicators' => ['persistent_attempt_behavior'],
                'bloom_demand' => BloomLevel::Apply,
                'dave_demand' => DaveLevel::Manipulation,
                'explanation' => 'Synthetic needs_support for M6-05.',
                'inference_rule' => 'fixture',
            ]);
            $evidence = $this->makeEvidence($userId, $courseId, $activityId, 'submission_rejected', now()->subMinutes($minutes));
            $state->validatedEvidence()->sync([$evidence->id]);
        }
    }

    private function makeState(int $userId, int $activityId, LearningStateValue $state, \DateTimeInterface $inferredAt, string $evidenceType, ?string $cognitive, int $courseId): LearningState
    {
        $record = LearningState::factory()->create([
            'user_id' => $userId,
            'activity_id' => $activityId,
            'state' => $state,
            'state_confidence' => StateConfidence::Medium,
            'inferred_at' => $inferredAt,
            'inference_key' => hash('sha256', uniqid($state->value, true)),
            'cognitive_indicator' => $cognitive,
            'psychomotor_indicator' => null,
            'behavioral_indicators' => ['persistent_attempt_behavior'],
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
            'explanation' => 'Synthetic state for M6-05 response explainability.',
            'inference_rule' => 'fixture_m6_05',
        ]);

        $evidence = $this->makeEvidence($userId, $courseId, $activityId, $evidenceType, $inferredAt);
        $record->validatedEvidence()->sync([$evidence->id]);

        return $record->fresh(['validatedEvidence.learningEvent']);
    }

    private function makeIntervention(int $userId, int $activityId, LearningState $state, \DateTimeInterface $createdAt): AdaptiveIntervention
    {
        $intervention = AdaptiveIntervention::factory()->create([
            'user_id' => $userId,
            'activity_id' => $activityId,
            'learning_state_id' => $state->id,
            'intervention_type' => InterventionType::GuidedRetry,
            'is_remedial' => true,
            'is_strong' => true,
            'selection_rule' => 'fixture_m6_05',
            'reason' => 'Synthetic intervention for M6-05.',
            'content' => 'Try again with guidance.',
            'intervention_key' => hash('sha256', uniqid('m6-05-intervention', true)),
            'metadata' => ['validated_evidence_ids' => $state->validatedEvidence->pluck('id')->values()->all(), 'provides_direct_answer' => false],
        ]);

        $intervention->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $intervention->fresh();
    }

    private function makeEvidence(int $userId, int $courseId, int $activityId, string $type, \DateTimeInterface $at): ValidatedEvidence
    {
        $event = LearningEvent::query()->create([
            'user_id' => $userId,
            'course_id' => $courseId,
            'activity_id' => $activityId,
            'event_type' => $type,
            'payload' => ['synthetic' => true],
            'occurred_at' => $at,
        ]);

        return ValidatedEvidence::query()->create([
            'user_id' => $userId,
            'activity_id' => $activityId,
            'learning_event_id' => $event->id,
            'evidence_category' => EvidenceCategory::Performance->value,
            'evidence_type' => $type,
            'observed_value' => ['summary' => $type],
            'context_summary' => [],
            'quality' => EvidenceQuality::Valid->value,
            'confidence' => EvidenceConfidence::High->value,
            'validation_reason' => 'Synthetic M6-05 evidence.',
            'validated_at' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function firstFinding(array $result): array
    {
        $findings = $result['findings'] ?? [];

        return is_array($findings) && $findings !== [] ? $findings[0] : [];
    }

    private function taskDemand(?string $bloom, ?string $cognitive, ?string $dave, ?string $psychomotor): bool
    {
        if ($bloom !== null && $cognitive === $bloom) {
            return false;
        }

        if ($dave !== null && $psychomotor === $dave) {
            return false;
        }

        return true;
    }

    private function learnerRef(string $scenarioId): string
    {
        return 'learner-'.substr(hash('sha256', 'm6-05|'.$scenarioId), 0, 12);
    }
}
