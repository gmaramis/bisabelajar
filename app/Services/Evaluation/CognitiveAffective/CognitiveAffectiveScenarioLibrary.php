<?php

namespace App\Services\Evaluation\CognitiveAffective;

use App\Enums\BloomLevel;
use App\Enums\DaveLevel;
use App\Enums\EvidenceCategory;
use App\Enums\EvidenceConfidence;
use App\Enums\EvidenceQuality;
use App\Enums\LearningStateValue;
use App\Services\Evaluation\LearningState\EvidenceSpec;

/**
 * Independently authored library of synthetic cognitive-affective validation
 * scenarios (M6-04).
 *
 * INDEPENDENCE BOUNDARY: depends only on domain enums and evaluation value
 * objects; it does not reference or execute any production inference/orchestration
 * service. Every expectation is hand-authored from scenario intent and the
 * documented, observable indicator vocabulary. Affective information is expressed
 * only as observable learning behavior — never a psychological/clinical label.
 * Data is synthetic.
 */
final class CognitiveAffectiveScenarioLibrary
{
    private const CONCEPT = 'loops';

    /**
     * @return list<CognitiveAffectiveScenario>
     */
    public function all(): array
    {
        $scenarios = [
            $this->cognitiveCorrective(),
            $this->cognitiveUnresolved(),
            $this->psychomotorUnresolved(),
            $this->persistentAttempt(),
            $this->persistentEngagement(),
            $this->reducedEngagement(),
            $this->insufficientUncertain(),
            $this->conflictingReview(),
            $this->divergenceFail(),
        ];

        usort($scenarios, fn (CognitiveAffectiveScenario $a, CognitiveAffectiveScenario $b): int => strcmp($a->scenarioId, $b->scenarioId));

        return $scenarios;
    }

    private function make(string $id, string $category, string $description, array $evidence, ExpectedIndicators $expected): CognitiveAffectiveScenario
    {
        return new CognitiveAffectiveScenario(
            scenarioId: $id,
            category: $category,
            description: $description,
            concept: self::CONCEPT,
            bloomDemand: BloomLevel::Apply,
            daveDemand: DaveLevel::Manipulation,
            evidence: $evidence,
            expected: $expected,
        );
    }

    private function perf(string $type, EvidenceQuality $quality, EvidenceConfidence $confidence): EvidenceSpec
    {
        return new EvidenceSpec($type, EvidenceCategory::Performance, $quality, $confidence);
    }

    private function cognitiveCorrective(): CognitiveAffectiveScenario
    {
        return $this->make(
            'CAV-COGNITIVE-CORRECTIVE-001', 'cognitive',
            'A rejection then an acceptance (corrective application).',
            [
                $this->perf('submission_rejected', EvidenceQuality::Valid, EvidenceConfidence::Medium),
                $this->perf('submission_accepted', EvidenceQuality::Valid, EvidenceConfidence::High),
            ],
            new ExpectedIndicators(
                cognitiveIndicator: 'corrective_application_observed',
                psychomotorIndicator: 'error_correction_then_successful_execution',
                requiredBehavioral: ['corrective_behavior'],
                expectedState: LearningStateValue::Progressing,
                rationale: 'A failure corrected by a later acceptance is observable corrective behavior.',
            ),
        );
    }

    private function cognitiveUnresolved(): CognitiveAffectiveScenario
    {
        return $this->make(
            'CAV-COGNITIVE-UNRESOLVED-001', 'cognitive',
            'Two rejections with no acceptance (repeated rejection / unresolved outcome).',
            [
                $this->perf('submission_rejected', EvidenceQuality::Valid, EvidenceConfidence::High),
                $this->perf('submission_rejected', EvidenceQuality::Valid, EvidenceConfidence::High),
            ],
            new ExpectedIndicators(
                cognitiveIndicator: 'unresolved_performance_outcome_observed',
                psychomotorIndicator: 'task_skill_demand_context_only',
                expectBehavioralEmpty: true,
                expectedState: LearningStateValue::NeedsSupport,
                rationale: 'Repeated unsuccessful attempts are an observable unresolved-outcome signal, not a diagnosis.',
            ),
        );
    }

    private function psychomotorUnresolved(): CognitiveAffectiveScenario
    {
        return $this->make(
            'CAV-PSYCHOMOTOR-UNRESOLVED-001', 'psychomotor',
            'Execution practice (code run) with a rejection and no acceptance.',
            [
                new EvidenceSpec('code_run', EvidenceCategory::Interaction, EvidenceQuality::Valid, EvidenceConfidence::Medium),
                $this->perf('submission_rejected', EvidenceQuality::Valid, EvidenceConfidence::Medium),
            ],
            new ExpectedIndicators(
                cognitiveIndicator: 'unresolved_performance_outcome_observed',
                psychomotorIndicator: 'execution_practice_with_unresolved_outcome',
                expectBehavioralEmpty: true,
                rationale: 'Runtime practice with an unresolved outcome is an observable psychomotor signal.',
            ),
        );
    }

    private function persistentAttempt(): CognitiveAffectiveScenario
    {
        return $this->make(
            'CAV-BEHAVIORAL-PERSISTENT-ATTEMPT-001', 'behavioral',
            'Rejection plus a repeated-submission-failures behavioral signal.',
            [
                $this->perf('submission_rejected', EvidenceQuality::Valid, EvidenceConfidence::Medium),
                new EvidenceSpec('repeated_submission_failures', EvidenceCategory::Behavioral, EvidenceQuality::ContextDependent, EvidenceConfidence::Medium),
            ],
            new ExpectedIndicators(
                cognitiveIndicator: 'unresolved_performance_outcome_observed',
                requiredBehavioral: ['persistent_attempt_behavior'],
                expectedState: LearningStateValue::NeedsSupport,
                rationale: 'Repeated attempts are observable persistence behavior, not psychological frustration.',
            ),
        );
    }

    private function persistentEngagement(): CognitiveAffectiveScenario
    {
        return $this->make(
            'CAV-BEHAVIORAL-PERSISTENT-ENGAGEMENT-001', 'behavioral',
            'An acceptance alongside execution activity (persistent engagement).',
            [
                $this->perf('submission_accepted', EvidenceQuality::Valid, EvidenceConfidence::High),
                new EvidenceSpec('code_run', EvidenceCategory::Interaction, EvidenceQuality::Valid, EvidenceConfidence::Medium),
            ],
            new ExpectedIndicators(
                cognitiveIndicator: 'successful_task_outcome_observed',
                psychomotorIndicator: 'successful_execution_observed',
                requiredBehavioral: ['persistent_engagement'],
                expectedState: LearningStateValue::Stable,
                rationale: 'Successful outcome with execution activity is observable persistent engagement.',
            ),
        );
    }

    private function reducedEngagement(): CognitiveAffectiveScenario
    {
        return $this->make(
            'CAV-BEHAVIORAL-REDUCED-ENGAGEMENT-001', 'behavioral',
            'Only an activity_started interaction (reduced activity engagement).',
            [
                new EvidenceSpec('activity_started', EvidenceCategory::Interaction, EvidenceQuality::Valid, EvidenceConfidence::Medium),
            ],
            new ExpectedIndicators(
                cognitiveIndicator: 'task_demand_context_only',
                psychomotorIndicator: 'task_skill_demand_context_only',
                requiredBehavioral: ['reduced_activity_engagement'],
                expectedState: LearningStateValue::InsufficientEvidence,
                rationale: 'Starting without further action is an observable reduced-engagement pattern, not disengagement diagnosis.',
            ),
        );
    }

    private function insufficientUncertain(): CognitiveAffectiveScenario
    {
        return $this->make(
            'CAV-INSUFFICIENT-UNCERTAIN-001', 'insufficient',
            'Only an uncertain system-context anomaly (timeout).',
            [
                new EvidenceSpec('execution_runtime_failure', EvidenceCategory::SystemContext, EvidenceQuality::Uncertain, EvidenceConfidence::Low),
            ],
            new ExpectedIndicators(
                expectCognitiveNull: true,
                expectPsychomotorNull: true,
                expectBehavioralEmpty: true,
                expectedState: LearningStateValue::InsufficientEvidence,
                rationale: 'Uncertain-only evidence produces no fabricated indicators.',
            ),
        );
    }

    private function conflictingReview(): CognitiveAffectiveScenario
    {
        return $this->make(
            'CAV-CONFLICTING-REVIEW-001', 'boundary',
            'Mixed signals: rejection, acceptance, and execution activity.',
            [
                $this->perf('submission_rejected', EvidenceQuality::Valid, EvidenceConfidence::Medium),
                $this->perf('submission_accepted', EvidenceQuality::Valid, EvidenceConfidence::High),
                new EvidenceSpec('code_run', EvidenceCategory::Interaction, EvidenceQuality::Valid, EvidenceConfidence::Medium),
            ],
            new ExpectedIndicators(
                cognitiveIndicator: 'corrective_application_observed',
                requiredBehavioral: ['corrective_behavior'],
                ambiguous: true,
                rationale: 'Conflicting observable signals are structurally valid but their construct interpretation needs expert REVIEW.',
            ),
        );
    }

    private function divergenceFail(): CognitiveAffectiveScenario
    {
        return $this->make(
            'CAV-DIVERGENCE-FAIL-001', 'cognitive',
            'A single acceptance, but the authored expectation asserts an unresolved-outcome indicator.',
            [
                $this->perf('submission_accepted', EvidenceQuality::Valid, EvidenceConfidence::High),
            ],
            new ExpectedIndicators(
                cognitiveIndicator: 'unresolved_performance_outcome_observed',
                rationale: 'Intentionally divergent expectation used to prove FAIL detection when the model disagrees.',
            ),
        );
    }
}
