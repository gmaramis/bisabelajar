<?php

namespace App\Services\Research;

use App\Enums\EvidenceCategory;
use App\Enums\EvidenceConfidence;
use App\Enums\EvidenceQuality;
use App\Enums\LearningStateTransitionType;
use App\Enums\LearningStateValue;
use App\Enums\WeakAreaClassification;
use App\Models\Activity;
use App\Models\LearningState;
use App\Models\NextLearningAction;
use App\Models\ValidatedEvidence;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Deterministic weak-area identification (M5-03).
 *
 * Aggregates evidence by Activity.concept within a course (activity_concept proxy).
 * Does not invent a competency model, run ML/LLM, generate reassessment tasks,
 * or claim psychological diagnosis / intervention effectiveness.
 */
final class WeakAreaIdentificationQuery
{
    private const FAILURE_EVIDENCE_TYPES = [
        'submission_rejected',
        'repeated_submission_failures',
    ];

    private const ACCEPTANCE_EVIDENCE_TYPES = [
        'submission_accepted',
    ];

    private const UNRESOLVED_COGNITIVE = 'unresolved_performance_outcome_observed';

    private const UNRESOLVED_PSYCHOMOTOR = 'execution_practice_with_unresolved_outcome';

    public function __construct(
        private readonly ResearchEvidenceQuery $researchEvidence,
        private readonly LearningStateTrajectoryQuery $trajectoryQuery,
    ) {}

    /**
     * Identify weak areas for a learner within one course.
     *
     * @return array<string, mixed>
     */
    public function forLearnerCourse(int $userId, int $courseId): array
    {
        $states = LearningState::query()
            ->with([
                'validatedEvidence.learningEvent',
                'activity.learningUnit.module.course',
                'nextLearningActions',
            ])
            ->where('user_id', $userId)
            ->whereHas('activity.learningUnit.module', function ($query) use ($courseId): void {
                $query->where('course_id', $courseId);
            })
            ->orderBy('inferred_at')
            ->orderBy('id')
            ->get();

        $groups = $states->groupBy(
            fn (LearningState $state): string => $this->learningAreaKey($state->activity)
        );

        $findings = $groups
            ->map(fn (Collection $group, string $areaKey): array => $this->analyzeArea(
                $userId,
                $courseId,
                $areaKey,
                $group->values(),
            ))
            ->sortBy(fn (array $finding): string => $finding['learning_area_key'])
            ->values()
            ->all();

        return [
            'research_learner_id' => $this->researchEvidence->researchLearnerId($userId),
            'learner_id' => $userId,
            'scope' => [
                'type' => 'course',
                'course_id' => $courseId,
            ],
            'learning_area_representation' => 'activity_concept',
            'learning_area_representation_note' => 'No explicit competency ID model exists; Activity.concept is used as the narrowest defensible learning-area proxy within a course. Empty concept falls back to learning_unit:{id}.',
            'findings' => $findings,
            'weak_areas' => array_values(array_filter(
                $findings,
                fn (array $finding): bool => in_array($finding['classification'], [
                    WeakAreaClassification::WeakPersistent->value,
                    WeakAreaClassification::WeakRepeatedFailure->value,
                    WeakAreaClassification::WeakUnresolved->value,
                ], true),
            )),
            'analysis_boundary' => $this->analysisBoundary(),
        ];
    }

    /**
     * Analyze the learning area of one activity (aggregates sibling activities sharing the same concept in-course).
     *
     * @return array<string, mixed>
     */
    public function forLearnerActivity(int $userId, int $activityId): array
    {
        $activity = Activity::query()
            ->with('learningUnit.module')
            ->findOrFail($activityId);

        $courseId = $activity->learningUnit?->module?->course_id;
        if ($courseId === null) {
            return [
                'research_learner_id' => $this->researchEvidence->researchLearnerId($userId),
                'learner_id' => $userId,
                'scope' => ['type' => 'activity', 'activity_id' => $activityId, 'course_id' => null],
                'finding' => null,
                'analysis_boundary' => $this->analysisBoundary(),
            ];
        }

        $areaKey = $this->learningAreaKey($activity);
        $courseResult = $this->forLearnerCourse($userId, $courseId);
        $finding = collect($courseResult['findings'])
            ->first(fn (array $row): bool => $row['learning_area_key'] === $areaKey);

        return [
            'research_learner_id' => $courseResult['research_learner_id'],
            'learner_id' => $userId,
            'scope' => [
                'type' => 'activity',
                'activity_id' => $activityId,
                'course_id' => $courseId,
                'learning_area_key' => $areaKey,
            ],
            'learning_area_representation' => $courseResult['learning_area_representation'],
            'finding' => $finding,
            'analysis_boundary' => $this->analysisBoundary(),
        ];
    }

    /**
     * @param  Collection<int, LearningState>  $states
     * @return array<string, mixed>
     */
    private function analyzeArea(int $userId, int $courseId, string $areaKey, Collection $states): array
    {
        $states = $states->sortBy([
            ['inferred_at', 'asc'],
            ['id', 'asc'],
        ])->values();

        $activities = $states
            ->map(fn (LearningState $state) => $state->activity)
            ->filter()
            ->unique('id')
            ->values();

        $activityIds = $activities->pluck('id')->sort()->values()->all();
        $representation = $this->learningAreaRepresentation($activities->first());

        $allEvidence = $states
            ->flatMap(fn (LearningState $state) => $state->validatedEvidence)
            ->unique('id')
            ->values();

        $usable = $allEvidence->filter(fn (ValidatedEvidence $evidence): bool => $this->isUsable($evidence))->values();
        $failures = $usable->filter(
            fn (ValidatedEvidence $evidence): bool => in_array($evidence->evidence_type, self::FAILURE_EVIDENCE_TYPES, true)
        )->values();
        $acceptances = $usable->filter(
            fn (ValidatedEvidence $evidence): bool => in_array($evidence->evidence_type, self::ACCEPTANCE_EVIDENCE_TYPES, true)
        )->values();

        $needsSupportStates = $states->filter(
            fn (LearningState $state): bool => $state->state === LearningStateValue::NeedsSupport
        )->values();

        $cognitiveUnresolvedStates = $states->filter(
            fn (LearningState $state): bool => $state->cognitive_indicator === self::UNRESOLVED_COGNITIVE
        )->values();

        $psychomotorUnresolvedStates = $states->filter(
            fn (LearningState $state): bool => $state->psychomotor_indicator === self::UNRESOLVED_PSYCHOMOTOR
        )->values();

        $behavioralIndicators = $states
            ->flatMap(function (LearningState $state): array {
                return is_array($state->behavioral_indicators) ? $state->behavioral_indicators : [];
            })
            ->unique()
            ->values()
            ->all();

        $hasPersistentAttempt = in_array('persistent_attempt_behavior', $behavioralIndicators, true);
        $hasCorrectiveBehavior = in_array('corrective_behavior', $behavioralIndicators, true);

        $sequence = $states->map(fn (LearningState $state): string => $state->state->value)->all();
        $transitions = $this->transitionsForStates($states);
        $persistentSupportTransitions = collect($transitions)
            ->where('transition_type', LearningStateTransitionType::PersistentSupportNeed->value)
            ->count();
        $recoveryTransitions = collect($transitions)->filter(
            fn (array $row): bool => in_array($row['transition_type'], [
                LearningStateTransitionType::PositiveTransition->value,
                LearningStateTransitionType::Stabilization->value,
            ], true)
        )->count();

        $failedRetries = NextLearningAction::query()
            ->where('user_id', $userId)
            ->whereIn('activity_id', $activityIds)
            ->where('retry_outcome', 'failure')
            ->count();

        /** @var LearningState|null $latest */
        $latest = $states->last();
        $latestIsRecovered = $latest !== null && in_array($latest->state, [
            LearningStateValue::Stable,
            LearningStateValue::Progressing,
        ], true);

        $signals = [
            'usable_evidence_count' => $usable->count(),
            'failure_evidence_count' => $failures->count(),
            'acceptance_evidence_count' => $acceptances->count(),
            'needs_support_state_count' => $needsSupportStates->count(),
            'cognitive_unresolved_state_count' => $cognitiveUnresolvedStates->count(),
            'psychomotor_unresolved_state_count' => $psychomotorUnresolvedStates->count(),
            'persistent_attempt_behavior' => $hasPersistentAttempt,
            'corrective_behavior' => $hasCorrectiveBehavior,
            'persistent_support_transitions' => $persistentSupportTransitions,
            'recovery_transitions' => $recoveryTransitions,
            'failed_retry_action_count' => $failedRetries,
            'latest_state' => $latest?->state->value,
        ];

        [$classification, $detectionRule, $explanation] = $this->classify(
            $signals,
            $latestIsRecovered,
            $sequence,
        );

        $bloomDemands = $activities
            ->map(fn (Activity $activity): ?string => $this->enumValue($activity->getBloomDemand()))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $daveDemands = $activities
            ->map(fn (Activity $activity): ?string => $this->enumValue($activity->getDaveDemand()))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $qualitySummary = [
            'valid' => $allEvidence->where('quality', EvidenceQuality::Valid)->count(),
            'context_dependent' => $allEvidence->where('quality', EvidenceQuality::ContextDependent)->count(),
            'uncertain' => $allEvidence->where('quality', EvidenceQuality::Uncertain)->count(),
            'system_context_excluded_from_strength' => $allEvidence
                ->where('evidence_category', EvidenceCategory::SystemContext)
                ->count(),
        ];

        $confidenceSummary = [
            'high' => $usable->where('confidence', EvidenceConfidence::High)->count(),
            'medium' => $usable->where('confidence', EvidenceConfidence::Medium)->count(),
            'low' => $usable->where('confidence', EvidenceConfidence::Low)->count(),
        ];

        return [
            'research_learner_id' => $this->researchEvidence->researchLearnerId($userId),
            'learner_id' => $userId,
            'course_id' => $courseId,
            'learning_area_key' => $areaKey,
            'learning_area_label' => $this->learningAreaLabel($activities->first(), $areaKey),
            'learning_area_representation' => $representation,
            'classification' => $classification->value,
            'is_weak_area' => in_array($classification, [
                WeakAreaClassification::WeakPersistent,
                WeakAreaClassification::WeakRepeatedFailure,
                WeakAreaClassification::WeakUnresolved,
            ], true),
            'evidence_count' => $usable->count(),
            'supporting_evidence_ids' => $usable->pluck('id')->sort()->values()->all(),
            'supporting_learning_state_ids' => $states->pluck('id')->values()->all(),
            'activity_ids' => $activityIds,
            'trajectory' => [
                'sequence' => $sequence,
                'transitions' => $transitions,
                'persistent_support_transitions' => $persistentSupportTransitions,
                'recovery_transitions' => $recoveryTransitions,
            ],
            'signals' => $signals,
            'bloom_demand_context' => $bloomDemands,
            'dave_demand_context' => $daveDemands,
            'bloom_semantics' => 'task_demand',
            'dave_semantics' => 'task_demand',
            'evidence_quality_summary' => $qualitySummary,
            'evidence_confidence_summary' => $confidenceSummary,
            'observable_indicators' => [
                'cognitive' => $cognitiveUnresolvedStates
                    ->pluck('cognitive_indicator')
                    ->unique()
                    ->values()
                    ->all(),
                'psychomotor' => $psychomotorUnresolvedStates
                    ->pluck('psychomotor_indicator')
                    ->unique()
                    ->values()
                    ->all(),
                'behavioral' => $behavioralIndicators,
            ],
            'detection_rule' => $detectionRule,
            'explanation' => $explanation,
            'identified_at' => now()->toIso8601String(),
            'claims_psychological_diagnosis' => false,
            'claims_learner_capability_from_bloom_dave' => false,
            'generates_reassessment_questions' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $signals
     * @param  list<string>  $sequence
     * @return array{0: WeakAreaClassification, 1: string, 2: string}
     */
    private function classify(array $signals, bool $latestIsRecovered, array $sequence): array
    {
        $failures = (int) $signals['failure_evidence_count'];
        $acceptances = (int) $signals['acceptance_evidence_count'];
        $needsSupport = (int) $signals['needs_support_state_count'];
        $cognitive = (int) $signals['cognitive_unresolved_state_count'];
        $psychomotor = (int) $signals['psychomotor_unresolved_state_count'];
        $persistentTransitions = (int) $signals['persistent_support_transitions'];
        $failedRetries = (int) $signals['failed_retry_action_count'];
        $usable = (int) $signals['usable_evidence_count'];

        // Pattern B: recovered after difficulty → not a current weak area.
        if ($latestIsRecovered && ($acceptances >= 1 || (int) $signals['recovery_transitions'] >= 1)) {
            return [
                WeakAreaClassification::NoCurrentWeakness,
                'latest_recovered_state + acceptance_or_positive_trajectory → no_current_weakness',
                'Evidence shows prior difficulty may have occurred, but the latest Learning State for this learning area is progressing/stable after acceptance or an observed positive trajectory pattern. This is not classified as a current weak area. Bloom/Dave remain task demand context only.',
            ];
        }

        // Strong pattern A: repeated needs_support / persistent trajectory.
        if ($needsSupport >= 2 && ($persistentTransitions >= 1 || $this->consecutiveNeedsSupport($sequence) >= 2)
            && $signals['latest_state'] === LearningStateValue::NeedsSupport->value) {
            return [
                WeakAreaClassification::WeakPersistent,
                'repeated_needs_support + persistent_support_trajectory → weak_persistent',
                'Evidence-derived finding: learning area shows repeated needs_support Learning States and a persistent support trajectory pattern. This indicates the area still needs strengthening. This is not a psychological diagnosis.',
            ];
        }

        // Repeated unsuccessful outcomes without resolution.
        if ($failures >= 2 && $acceptances === 0
            && ($needsSupport >= 1 || $failedRetries >= 1 || $signals['persistent_attempt_behavior'] === true)
            && $signals['latest_state'] === LearningStateValue::NeedsSupport->value) {
            return [
                WeakAreaClassification::WeakRepeatedFailure,
                'repeated_unsuccessful_outcomes + unresolved_latest_state → weak_repeated_failure',
                'Evidence-derived finding: repeated unsuccessful performance outcomes without acceptance remain unresolved for this learning area. Single-activity failure alone is not used; converging repeated failure evidence is required.',
            ];
        }

        // Unresolved cognitive/psychomotor indicators with support need.
        if ($needsSupport >= 1 && ($cognitive >= 2 || $psychomotor >= 2 || ($cognitive >= 1 && $psychomotor >= 1) || ($cognitive >= 1 && $failedRetries >= 1))) {
            if ($signals['latest_state'] === LearningStateValue::NeedsSupport->value) {
                return [
                    WeakAreaClassification::WeakUnresolved,
                    'unresolved_cognitive_or_psychomotor + needs_support → weak_unresolved',
                    'Evidence-derived finding: unresolved cognitive and/or psychomotor indicators converge with needs_support for this learning area. Observable indicators only; no psychological inference.',
                ];
            }
        }

        // False-positive guard: single failure / thin evidence.
        if ($usable === 0 || ($failures <= 1 && $needsSupport <= 1 && $cognitive <= 1 && $psychomotor <= 1 && $persistentTransitions === 0)) {
            return [
                WeakAreaClassification::InsufficientEvidence,
                'single_or_thin_evidence → insufficient_evidence',
                'Available evidence is insufficient to declare a competency/learning-area weakness. A single unsuccessful outcome or thin signal does not justify a weak-area finding.',
            ];
        }

        if ($signals['latest_state'] === LearningStateValue::NeedsSupport->value && $needsSupport >= 2) {
            return [
                WeakAreaClassification::WeakPersistent,
                'repeated_needs_support_without_recovery → weak_persistent',
                'Evidence-derived finding: multiple needs_support states remain without recovery for this learning area.',
            ];
        }

        return [
            WeakAreaClassification::InsufficientEvidence,
            'ambiguous_pattern → insufficient_evidence',
            'Evidence pattern is ambiguous for a V1 weak-area classification. No weak area is declared.',
        ];
    }

    /**
     * @param  list<string>  $sequence
     */
    private function consecutiveNeedsSupport(array $sequence): int
    {
        $max = 0;
        $run = 0;
        foreach ($sequence as $state) {
            if ($state === LearningStateValue::NeedsSupport->value) {
                $run++;
                $max = max($max, $run);
            } else {
                $run = 0;
            }
        }

        return $max;
    }

    /**
     * @param  Collection<int, LearningState>  $states
     * @return list<array<string, mixed>>
     */
    private function transitionsForStates(Collection $states): array
    {
        $transitions = [];
        for ($i = 1; $i < $states->count(); $i++) {
            /** @var LearningState $from */
            $from = $states[$i - 1];
            /** @var LearningState $to */
            $to = $states[$i];
            $type = $this->trajectoryQuery->classifyTransition($from->state, $to->state);
            $transitions[] = [
                'from_learning_state_id' => $from->id,
                'to_learning_state_id' => $to->id,
                'from_state' => $from->state->value,
                'to_state' => $to->state->value,
                'transition_type' => $type->value,
                'claims_causal_improvement' => false,
            ];
        }

        return $transitions;
    }

    private function isUsable(ValidatedEvidence $evidence): bool
    {
        if ($evidence->quality === EvidenceQuality::Uncertain) {
            return false;
        }

        if ($evidence->evidence_category === EvidenceCategory::SystemContext) {
            return false;
        }

        return true;
    }

    private function learningAreaKey(?Activity $activity): string
    {
        if ($activity === null) {
            return 'unknown';
        }

        $concept = trim((string) ($activity->getConcept() ?? ''));
        if ($concept !== '') {
            return 'concept:'.mb_strtolower($concept);
        }

        $unitId = $activity->learning_unit_id;

        return 'learning_unit:'.($unitId ?? 'unknown');
    }

    private function learningAreaRepresentation(?Activity $activity): string
    {
        if ($activity === null) {
            return 'unknown';
        }

        $concept = trim((string) ($activity->getConcept() ?? ''));

        return $concept !== '' ? 'activity_concept' : 'learning_unit';
    }

    private function learningAreaLabel(?Activity $activity, string $areaKey): string
    {
        if ($activity === null) {
            return $areaKey;
        }

        $concept = trim((string) ($activity->getConcept() ?? ''));
        if ($concept !== '') {
            return $concept;
        }

        return $activity->learningUnit?->title ?? $areaKey;
    }

    /**
     * @return array<string, bool>
     */
    private function analysisBoundary(): array
    {
        return [
            'identifies_evidence_derived_weak_areas' => true,
            'claims_psychological_diagnosis' => false,
            'claims_permanent_learner_label' => false,
            'claims_learner_bloom_capability' => false,
            'claims_learner_dave_capability' => false,
            'generates_reassessment_questions' => false,
            'performs_intervention_effectiveness' => false,
            'performs_contextual_variation_analysis' => false,
            'uses_ml_or_llm' => false,
            'mutates_m4' => false,
            'persists_weak_area_table' => false,
        ];
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof UnitEnum) {
            return $value->value;
        }

        return (string) $value;
    }
}
