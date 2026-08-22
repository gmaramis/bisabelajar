<?php

namespace App\Services\Research;

use App\Enums\BloomLevel;
use App\Enums\DaveLevel;
use App\Enums\EvidenceCategory;
use App\Enums\EvidenceConfidence;
use App\Enums\EvidenceQuality;
use App\Enums\LearningStateValue;
use App\Enums\StateConfidence;
use App\Models\Activity;
use App\Models\LearningState;
use App\Models\ValidatedEvidence;
use Illuminate\Support\Collection;

/**
 * Deterministic Learning State inference from fused ValidatedEvidence (M4-T03 V1).
 *
 * Does not bypass M4-T02. Does not diagnose psychological states.
 * Does not deliver adaptive intervention or recommendations.
 */
final class LearningStateInferenceService
{
    /**
     * Infer (or refresh) Learning State for one learner on one activity.
     *
     * Idempotent for the same evidence set via inference_key.
     */
    public function inferForLearnerActivity(int $userId, int $activityId): LearningState
    {
        $activity = Activity::query()->findOrFail($activityId);

        $evidence = ValidatedEvidence::query()
            ->where('user_id', $userId)
            ->where('activity_id', $activityId)
            ->orderBy('id')
            ->get();

        $fusion = $this->fuse($evidence, $activity);
        $inferenceKey = $this->inferenceKey($userId, $activityId, $evidence);

        $state = LearningState::query()->updateOrCreate(
            ['inference_key' => $inferenceKey],
            [
                'user_id' => $userId,
                'activity_id' => $activityId,
                'state' => $fusion['state']->value,
                'state_confidence' => $fusion['state_confidence']->value,
                'bloom_demand' => $fusion['bloom_demand']?->value,
                'dave_demand' => $fusion['dave_demand']?->value,
                'cognitive_indicator' => $fusion['cognitive_indicator'],
                'psychomotor_indicator' => $fusion['psychomotor_indicator'],
                'behavioral_indicators' => $fusion['behavioral_indicators'],
                'fusion_summary' => $fusion['fusion_summary'],
                'explanation' => $fusion['explanation'],
                'inference_rule' => $fusion['inference_rule'],
                'inferred_at' => now(),
            ],
        );

        $state->validatedEvidence()->sync($evidence->pluck('id')->all());

        return $state->fresh(['validatedEvidence', 'activity', 'user']);
    }

    /**
     * @param  Collection<int, ValidatedEvidence>  $evidence
     * @return array{
     *     state: LearningStateValue,
     *     state_confidence: StateConfidence,
     *     bloom_demand: ?BloomLevel,
     *     dave_demand: ?DaveLevel,
     *     cognitive_indicator: ?string,
     *     psychomotor_indicator: ?string,
     *     behavioral_indicators: list<string>,
     *     fusion_summary: array<string, mixed>,
     *     explanation: string,
     *     inference_rule: string
     * }
     */
    private function fuse(Collection $evidence, Activity $activity): array
    {
        $bloomDemand = $activity->getBloomDemand();
        $daveDemand = $activity->getDaveDemand();

        $usable = $evidence->filter(fn (ValidatedEvidence $item): bool => $this->isUsable($item))->values();
        $uncertainOnly = $evidence->filter(
            fn (ValidatedEvidence $item): bool => $item->quality === EvidenceQuality::Uncertain
        )->values();

        $types = $usable->pluck('evidence_type')->all();
        $categories = $usable->pluck('evidence_category')->map(
            fn ($category) => $category instanceof EvidenceCategory ? $category->value : (string) $category
        )->all();

        $hasAcceptance = in_array('submission_accepted', $types, true);
        $hasRejection = in_array('submission_rejected', $types, true);
        $hasRepeatedFailures = in_array('repeated_submission_failures', $types, true);
        $hasRepeatedExecution = in_array('repeated_execution', $types, true);
        $hasCompletion = in_array('activity_completed', $types, true);
        $hasStartOnly = in_array('activity_started', $types, true)
            && ! $hasAcceptance
            && ! $hasRejection
            && ! $hasCompletion
            && ! in_array('code_run', $types, true)
            && ! in_array('code_submit', $types, true);

        $cognitiveIndicator = $this->cognitiveIndicator($usable, $hasAcceptance, $hasRejection, $bloomDemand);
        $psychomotorIndicator = $this->psychomotorIndicator($usable, $hasAcceptance, $hasRejection, $daveDemand);
        $behavioralIndicators = $this->behavioralIndicators(
            $usable,
            $hasAcceptance,
            $hasRejection,
            $hasRepeatedFailures,
            $hasRepeatedExecution,
            $hasCompletion,
            $hasStartOnly,
        );

        $fusionSummary = [
            'total_evidence' => $evidence->count(),
            'usable_count' => $usable->count(),
            'uncertain_count' => $uncertainOnly->count(),
            'context_dependent_count' => $usable->filter(
                fn (ValidatedEvidence $item): bool => $item->quality === EvidenceQuality::ContextDependent
            )->count(),
            'valid_count' => $usable->filter(
                fn (ValidatedEvidence $item): bool => $item->quality === EvidenceQuality::Valid
            )->count(),
            'high_confidence_count' => $usable->filter(
                fn (ValidatedEvidence $item): bool => $item->confidence === EvidenceConfidence::High
            )->count(),
            'medium_confidence_count' => $usable->filter(
                fn (ValidatedEvidence $item): bool => $item->confidence === EvidenceConfidence::Medium
            )->count(),
            'low_confidence_count' => $usable->filter(
                fn (ValidatedEvidence $item): bool => $item->confidence === EvidenceConfidence::Low
            )->count(),
            'categories' => array_values(array_unique($categories)),
            'evidence_types' => array_values(array_unique($types)),
            'evidence_ids' => $evidence->pluck('id')->values()->all(),
            'bloom_demand' => $bloomDemand?->value,
            'dave_demand' => $daveDemand?->value,
            'cognitive_indicator' => $cognitiveIndicator,
            'psychomotor_indicator' => $psychomotorIndicator,
            'behavioral_indicators' => $behavioralIndicators,
        ];

        if (! $this->hasSufficientEvidence($usable, $hasAcceptance, $hasRejection, $hasRepeatedFailures, $hasCompletion)) {
            return $this->result(
                LearningStateValue::InsufficientEvidence,
                $this->stateConfidenceForInsufficient($evidence, $usable),
                $bloomDemand,
                $daveDemand,
                $cognitiveIndicator,
                $psychomotorIndicator,
                $behavioralIndicators,
                $fusionSummary,
                'insufficient_evidence_minimal_usable',
                $this->explainInsufficient($bloomDemand, $daveDemand, $fusionSummary),
            );
        }

        if ($hasRejection && $hasAcceptance) {
            return $this->result(
                LearningStateValue::Progressing,
                $this->stateConfidenceForPattern($usable, strong: true),
                $bloomDemand,
                $daveDemand,
                $cognitiveIndicator,
                $psychomotorIndicator,
                $behavioralIndicators,
                $fusionSummary,
                'progressing_corrective_success',
                $this->explainProgressing($bloomDemand, $daveDemand, $behavioralIndicators, $fusionSummary),
            );
        }

        if ($hasRepeatedFailures && ! $hasAcceptance) {
            return $this->result(
                LearningStateValue::NeedsSupport,
                $this->stateConfidenceForPattern($usable, strong: $hasRepeatedFailures),
                $bloomDemand,
                $daveDemand,
                $cognitiveIndicator,
                $psychomotorIndicator,
                $behavioralIndicators,
                $fusionSummary,
                'needs_support_repeated_failures',
                $this->explainNeedsSupport($bloomDemand, $daveDemand, $behavioralIndicators, $fusionSummary),
            );
        }

        if ($hasRejection && ! $hasAcceptance && $this->rejectionCount($usable) >= 2) {
            return $this->result(
                LearningStateValue::NeedsSupport,
                $this->stateConfidenceForPattern($usable, strong: false),
                $bloomDemand,
                $daveDemand,
                $cognitiveIndicator,
                $psychomotorIndicator,
                $behavioralIndicators,
                $fusionSummary,
                'needs_support_multiple_rejections',
                $this->explainNeedsSupport($bloomDemand, $daveDemand, $behavioralIndicators, $fusionSummary),
            );
        }

        if ($hasAcceptance && ! $hasRejection) {
            return $this->result(
                LearningStateValue::Stable,
                $this->stateConfidenceForPattern($usable, strong: true),
                $bloomDemand,
                $daveDemand,
                $cognitiveIndicator,
                $psychomotorIndicator,
                $behavioralIndicators,
                $fusionSummary,
                'stable_successful_outcome',
                $this->explainStable($bloomDemand, $daveDemand, $behavioralIndicators, $fusionSummary),
            );
        }

        if ($hasCompletion && ! $hasRejection) {
            return $this->result(
                LearningStateValue::Stable,
                $this->stateConfidenceForPattern($usable, strong: false),
                $bloomDemand,
                $daveDemand,
                $cognitiveIndicator,
                $psychomotorIndicator,
                $behavioralIndicators,
                $fusionSummary,
                'stable_completion_without_failure_pattern',
                $this->explainStable($bloomDemand, $daveDemand, $behavioralIndicators, $fusionSummary),
            );
        }

        return $this->result(
            LearningStateValue::InsufficientEvidence,
            StateConfidence::Low,
            $bloomDemand,
            $daveDemand,
            $cognitiveIndicator,
            $psychomotorIndicator,
            $behavioralIndicators,
            $fusionSummary,
            'insufficient_evidence_ambiguous_pattern',
            $this->explainInsufficient($bloomDemand, $daveDemand, $fusionSummary),
        );
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

    /**
     * @param  Collection<int, ValidatedEvidence>  $usable
     */
    private function hasSufficientEvidence(
        Collection $usable,
        bool $hasAcceptance,
        bool $hasRejection,
        bool $hasRepeatedFailures,
        bool $hasCompletion,
    ): bool {
        if ($usable->isEmpty()) {
            return false;
        }

        $performanceOrBehavioral = $usable->filter(
            fn (ValidatedEvidence $item): bool => in_array(
                $item->evidence_category,
                [EvidenceCategory::Performance, EvidenceCategory::Behavioral],
                true
            )
        );

        if ($performanceOrBehavioral->isEmpty() && ! $hasCompletion) {
            return false;
        }

        if ($performanceOrBehavioral->every(
            fn (ValidatedEvidence $item): bool => $item->confidence === EvidenceConfidence::Low
        ) && ! $hasAcceptance && ! $hasRepeatedFailures) {
            return false;
        }

        return $hasAcceptance || $hasRejection || $hasRepeatedFailures || $hasCompletion;
    }

    /**
     * @param  Collection<int, ValidatedEvidence>  $usable
     */
    private function cognitiveIndicator(
        Collection $usable,
        bool $hasAcceptance,
        bool $hasRejection,
        ?BloomLevel $bloomDemand,
    ): ?string {
        if ($usable->isEmpty()) {
            return null;
        }

        if ($hasRejection && $hasAcceptance) {
            return 'corrective_application_observed';
        }

        if ($hasAcceptance && ! $hasRejection) {
            return 'successful_task_outcome_observed';
        }

        if ($hasRejection && ! $hasAcceptance) {
            return 'unresolved_performance_outcome_observed';
        }

        if ($bloomDemand !== null && $usable->isNotEmpty()) {
            return 'task_demand_context_only';
        }

        return null;
    }

    /**
     * @param  Collection<int, ValidatedEvidence>  $usable
     */
    private function psychomotorIndicator(
        Collection $usable,
        bool $hasAcceptance,
        bool $hasRejection,
        ?DaveLevel $daveDemand,
    ): ?string {
        if ($usable->isEmpty()) {
            return null;
        }

        $hasRuntimePractice = $usable->contains(
            fn (ValidatedEvidence $item): bool => in_array($item->evidence_type, ['code_run', 'code_submit', 'repeated_execution'], true)
        );

        if ($hasRejection && $hasAcceptance) {
            return 'error_correction_then_successful_execution';
        }

        if ($hasAcceptance) {
            return 'successful_execution_observed';
        }

        if ($hasRuntimePractice && $hasRejection) {
            return 'execution_practice_with_unresolved_outcome';
        }

        if ($daveDemand !== null && $usable->isNotEmpty()) {
            return 'task_skill_demand_context_only';
        }

        return null;
    }

    /**
     * @param  Collection<int, ValidatedEvidence>  $usable
     * @return list<string>
     */
    private function behavioralIndicators(
        Collection $usable,
        bool $hasAcceptance,
        bool $hasRejection,
        bool $hasRepeatedFailures,
        bool $hasRepeatedExecution,
        bool $hasCompletion,
        bool $hasStartOnly,
    ): array {
        $indicators = [];

        if ($hasRepeatedFailures || ($hasRejection && ($hasRepeatedExecution || $usable->where('evidence_type', 'code_submit')->count() >= 1))) {
            if ($hasRejection && ! $hasAcceptance) {
                $indicators[] = 'persistent_attempt_behavior';
            }
        }

        if ($hasRejection && $hasAcceptance) {
            $indicators[] = 'corrective_behavior';
        }

        if ($hasCompletion || ($hasAcceptance && $usable->contains(
            fn (ValidatedEvidence $item): bool => in_array($item->evidence_type, ['code_run', 'code_submit', 'activity_started'], true)
        ))) {
            $indicators[] = 'persistent_engagement';
        }

        if ($hasStartOnly) {
            $indicators[] = 'reduced_activity_engagement';
        }

        return array_values(array_unique($indicators));
    }

    /**
     * @param  Collection<int, ValidatedEvidence>  $usable
     */
    private function rejectionCount(Collection $usable): int
    {
        return $usable->where('evidence_type', 'submission_rejected')->count();
    }

    /**
     * @param  Collection<int, ValidatedEvidence>  $evidence
     * @param  Collection<int, ValidatedEvidence>  $usable
     */
    private function stateConfidenceForInsufficient(Collection $evidence, Collection $usable): StateConfidence
    {
        if ($evidence->isEmpty()) {
            return StateConfidence::High;
        }

        if ($usable->isEmpty()) {
            return StateConfidence::Medium;
        }

        return StateConfidence::Low;
    }

    /**
     * @param  Collection<int, ValidatedEvidence>  $usable
     */
    private function stateConfidenceForPattern(Collection $usable, bool $strong): StateConfidence
    {
        $validCount = $usable->filter(
            fn (ValidatedEvidence $item): bool => $item->quality === EvidenceQuality::Valid
        )->count();
        $highCount = $usable->filter(
            fn (ValidatedEvidence $item): bool => $item->confidence === EvidenceConfidence::High
        )->count();
        $contextDependentCount = $usable->filter(
            fn (ValidatedEvidence $item): bool => $item->quality === EvidenceQuality::ContextDependent
        )->count();

        if ($strong && $validCount >= 2 && $highCount >= 1 && $contextDependentCount === 0) {
            return StateConfidence::High;
        }

        if ($strong && ($validCount + $contextDependentCount) >= 2) {
            return StateConfidence::Medium;
        }

        if ($contextDependentCount > 0 && $validCount === 0) {
            return StateConfidence::Low;
        }

        return $strong ? StateConfidence::Medium : StateConfidence::Low;
    }

    /**
     * @param  Collection<int, ValidatedEvidence>  $evidence
     */
    private function inferenceKey(int $userId, int $activityId, Collection $evidence): string
    {
        $ids = $evidence->pluck('id')->sort()->values()->implode(',');

        return hash('sha256', $userId.'|'.$activityId.'|'.$ids);
    }

    /**
     * @param  list<string>  $behavioralIndicators
     * @param  array<string, mixed>  $fusionSummary
     * @return array{
     *     state: LearningStateValue,
     *     state_confidence: StateConfidence,
     *     bloom_demand: ?BloomLevel,
     *     dave_demand: ?DaveLevel,
     *     cognitive_indicator: ?string,
     *     psychomotor_indicator: ?string,
     *     behavioral_indicators: list<string>,
     *     fusion_summary: array<string, mixed>,
     *     explanation: string,
     *     inference_rule: string
     * }
     */
    private function result(
        LearningStateValue $state,
        StateConfidence $stateConfidence,
        ?BloomLevel $bloomDemand,
        ?DaveLevel $daveDemand,
        ?string $cognitiveIndicator,
        ?string $psychomotorIndicator,
        array $behavioralIndicators,
        array $fusionSummary,
        string $inferenceRule,
        string $explanation,
    ): array {
        return [
            'state' => $state,
            'state_confidence' => $stateConfidence,
            'bloom_demand' => $bloomDemand,
            'dave_demand' => $daveDemand,
            'cognitive_indicator' => $cognitiveIndicator,
            'psychomotor_indicator' => $psychomotorIndicator,
            'behavioral_indicators' => $behavioralIndicators,
            'fusion_summary' => $fusionSummary,
            'explanation' => $explanation,
            'inference_rule' => $inferenceRule,
        ];
    }

    /**
     * @param  array<string, mixed>  $fusionSummary
     */
    private function explainInsufficient(?BloomLevel $bloomDemand, ?DaveLevel $daveDemand, array $fusionSummary): string
    {
        return implode(' ', [
            'Learning State: insufficient_evidence.',
            'Cognitive demand: '.($bloomDemand?->value ?? 'unknown').'.',
            'Psychomotor demand: '.($daveDemand?->value ?? 'unknown').'.',
            'Usable validated evidence count: '.$fusionSummary['usable_count'].'.',
            'Uncertain evidence is not treated as valid support.',
            'Reason: validated evidence is insufficient for a responsible learning-state inference.',
            'This explanation describes evidence coverage, not a psychological diagnosis.',
        ]);
    }

    /**
     * @param  list<string>  $behavioralIndicators
     * @param  array<string, mixed>  $fusionSummary
     */
    private function explainProgressing(
        ?BloomLevel $bloomDemand,
        ?DaveLevel $daveDemand,
        array $behavioralIndicators,
        array $fusionSummary,
    ): string {
        return implode(' ', [
            'Learning State: progressing.',
            'Cognitive demand: '.($bloomDemand?->value ?? 'unknown').'.',
            'Psychomotor demand: '.($daveDemand?->value ?? 'unknown').'.',
            'Cognitive indicator: '.($fusionSummary['cognitive_indicator'] ?? 'none').'.',
            'Psychomotor indicator: '.($fusionSummary['psychomotor_indicator'] ?? 'none').'.',
            'Observable behavioral indicators: '.(empty($behavioralIndicators) ? 'none' : implode(', ', $behavioralIndicators)).'.',
            'Observed evidence includes an initial performance failure followed by a later accepted submission.',
            'Reason: fused validated evidence indicates successful correction within the expected task demand.',
            'This explanation describes observable evidence patterns, not motivation or affective diagnosis.',
        ]);
    }

    /**
     * @param  list<string>  $behavioralIndicators
     * @param  array<string, mixed>  $fusionSummary
     */
    private function explainNeedsSupport(
        ?BloomLevel $bloomDemand,
        ?DaveLevel $daveDemand,
        array $behavioralIndicators,
        array $fusionSummary,
    ): string {
        return implode(' ', [
            'Learning State: needs_support.',
            'Cognitive demand: '.($bloomDemand?->value ?? 'unknown').'.',
            'Psychomotor demand: '.($daveDemand?->value ?? 'unknown').'.',
            'Cognitive indicator: '.($fusionSummary['cognitive_indicator'] ?? 'none').'.',
            'Psychomotor indicator: '.($fusionSummary['psychomotor_indicator'] ?? 'none').'.',
            'Observable behavioral indicators: '.(empty($behavioralIndicators) ? 'none' : implode(', ', $behavioralIndicators)).'.',
            'Observed evidence shows repeated unresolved performance failures without a later accepted submission.',
            'Reason: fused validated evidence indicates a difficulty pattern strong enough that the learner may need support.',
            'needs_support is a learning-state label, not an adaptive intervention.',
            'This explanation does not diagnose frustration, confusion, or other psychological states.',
        ]);
    }

    /**
     * @param  list<string>  $behavioralIndicators
     * @param  array<string, mixed>  $fusionSummary
     */
    private function explainStable(
        ?BloomLevel $bloomDemand,
        ?DaveLevel $daveDemand,
        array $behavioralIndicators,
        array $fusionSummary,
    ): string {
        return implode(' ', [
            'Learning State: stable.',
            'Cognitive demand: '.($bloomDemand?->value ?? 'unknown').'.',
            'Psychomotor demand: '.($daveDemand?->value ?? 'unknown').'.',
            'Cognitive indicator: '.($fusionSummary['cognitive_indicator'] ?? 'none').'.',
            'Psychomotor indicator: '.($fusionSummary['psychomotor_indicator'] ?? 'none').'.',
            'Observable behavioral indicators: '.(empty($behavioralIndicators) ? 'none' : implode(', ', $behavioralIndicators)).'.',
            'Observed evidence shows a successful outcome without a strong corrective-failure pattern.',
            'Reason: fused validated evidence indicates relatively stable task performance within the expected demand.',
            'This explanation describes observable evidence patterns, not psychological diagnosis.',
        ]);
    }
}
