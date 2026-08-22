<?php

namespace App\Services\Evaluation\Intervention;

use App\Enums\BloomLevel;
use App\Enums\DaveLevel;
use App\Enums\EvidenceCategory;
use App\Enums\EvidenceConfidence;
use App\Enums\EvidenceQuality;
use App\Enums\InterventionResponseClassification;
use App\Enums\InterventionType;
use App\Enums\LearningStateValue;
use App\Enums\NextLearningActionType;
use App\Enums\ObservedImprovementSignal;
use App\Enums\ReassessmentCandidateStatus;
use App\Enums\WeakAreaClassification;
use App\Services\Evaluation\LearningState\EvidenceSpec;

/**
 * Independently authored library of synthetic M6-03 evaluation scenarios covering
 * intervention selection, next action, reassessment, and intervention response.
 *
 * INDEPENDENCE BOUNDARY: this class depends only on domain enums and evaluation
 * value objects. It does not reference or execute any production intervention,
 * next-action, reassessment, or orchestration service. Every expectation is
 * hand-authored from scenario intent and documented M4/M5 behavior. Data is synthetic.
 */
final class InterventionScenarioLibrary
{
    private const CONCEPT = 'loops';

    /**
     * @return list<InterventionEvaluationScenario>
     */
    public function all(): array
    {
        $scenarios = array_merge(
            $this->interventionScenarios(),
            $this->nextActionScenarios(),
            $this->reassessmentScenarios(),
            $this->responseScenarios(),
        );

        usort($scenarios, fn (InterventionEvaluationScenario $a, InterventionEvaluationScenario $b): int => strcmp($a->scenarioId(), $b->scenarioId()));

        return $scenarios;
    }

    /**
     * @return list<InterventionEvaluationScenario>
     */
    private function interventionScenarios(): array
    {
        return [
            new InterventionScenario(
                'IEV-INTV-PERSISTENT-001', 'intervention_selection',
                'Needs_support from a rejection plus a repeated-failures behavioral signal.',
                self::CONCEPT, BloomLevel::Apply, DaveLevel::Manipulation,
                [
                    new EvidenceSpec('submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::Medium),
                    new EvidenceSpec('repeated_submission_failures', EvidenceCategory::Behavioral, EvidenceQuality::ContextDependent, EvidenceConfidence::Medium),
                ],
                new ExpectedIntervention(
                    expectRemedial: true, expectStrong: true, expectSocratic: true,
                    interventionType: InterventionType::Hint, selectionRule: 'needs_support_persistent_attempt_hint',
                    rationale: 'Persistent attempt behavior warrants a brief remedial hint with a next-step Socratic prompt.',
                ),
            ),
            new InterventionScenario(
                'IEV-INTV-COGNITIVE-001', 'intervention_selection',
                'Needs_support from two rejections with no acceptance (cognitive branch).',
                self::CONCEPT, BloomLevel::Apply, DaveLevel::Manipulation,
                [
                    new EvidenceSpec('submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High),
                    new EvidenceSpec('submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High),
                ],
                new ExpectedIntervention(
                    expectRemedial: true, expectStrong: true, expectSocratic: true,
                    interventionType: InterventionType::SocraticQuestion, selectionRule: 'needs_support_cognitive_socratic',
                    rationale: 'Unresolved cognitive outcome selects a Socratic question rather than a direct answer.',
                ),
            ),
            new InterventionScenario(
                'IEV-INTV-STABLE-001', 'intervention_selection',
                'Stable from a single accepted submission (non-remedial reinforcement).',
                self::CONCEPT, BloomLevel::Apply, DaveLevel::Manipulation,
                [
                    new EvidenceSpec('submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High),
                ],
                new ExpectedIntervention(
                    expectRemedial: false, expectStrong: false, expectSocratic: false,
                    interventionType: InterventionType::Reinforcement, selectionRule: 'stable_reinforcement',
                    rationale: 'A stable outcome receives brief reinforcement, not a remedial intervention.',
                ),
            ),
            new InterventionScenario(
                'IEV-INTV-INSUFFICIENT-001', 'insufficient_evidence',
                'No evidence: no strong remedial intervention is issued.',
                self::CONCEPT, BloomLevel::Apply, DaveLevel::Manipulation,
                [],
                new ExpectedIntervention(
                    expectRemedial: false, expectStrong: false, expectSocratic: false,
                    interventionType: InterventionType::Hint, selectionRule: 'insufficient_evidence_no_strong_intervention',
                    rationale: 'With insufficient evidence, only a soft non-remedial hint is issued.',
                ),
            ),
            new InterventionScenario(
                'IEV-INTV-DIVERGENCE-FAIL-001', 'intervention_selection',
                'Stable outcome, but the authored expectation asserts a remedial Socratic intervention.',
                self::CONCEPT, BloomLevel::Apply, DaveLevel::Manipulation,
                [
                    new EvidenceSpec('submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High),
                ],
                new ExpectedIntervention(
                    expectRemedial: true, expectStrong: true, expectSocratic: true,
                    interventionType: InterventionType::SocraticQuestion,
                    rationale: 'Intentionally divergent expectation used to prove FAIL detection when T04 disagrees.',
                ),
            ),
        ];
    }

    /**
     * @return list<InterventionEvaluationScenario>
     */
    private function nextActionScenarios(): array
    {
        return [
            new NextActionScenario(
                'IEV-ACTION-GUIDED-RETRY-001', 'next_action',
                'Needs_support with intervention and no retry yet → guided retry.',
                self::CONCEPT, BloomLevel::Apply, DaveLevel::Manipulation,
                [
                    new EvidenceSpec('submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::Medium),
                    new EvidenceSpec('repeated_submission_failures', EvidenceCategory::Behavioral, EvidenceQuality::ContextDependent, EvidenceConfidence::Medium),
                ],
                createIntervention: true, retryOutcome: null,
                expected: new ExpectedNextAction(
                    action: NextLearningActionType::GuidedRetry,
                    acceptableActions: [NextLearningActionType::GuidedRetry, NextLearningActionType::PracticeAgain],
                    rationale: 'After a remedial intervention with no retry yet, the next step is a guided retry.',
                ),
            ),
            new NextActionScenario(
                'IEV-ACTION-COLLECT-001', 'boundary',
                'Insufficient evidence → collect more evidence.',
                self::CONCEPT, BloomLevel::Apply, DaveLevel::Manipulation,
                [],
                createIntervention: false, retryOutcome: null,
                expected: new ExpectedNextAction(
                    action: NextLearningActionType::CollectMoreEvidence,
                    rationale: 'With insufficient evidence the only responsible action is to collect more.',
                ),
            ),
        ];
    }

    /**
     * @return list<InterventionEvaluationScenario>
     */
    private function reassessmentScenarios(): array
    {
        return [
            new ReassessmentScenario(
                'IEV-REASSESS-VALIDATED-001', 'reassessment',
                'Persistent weak area (real weak-area query) yields an eligible, validated candidate.',
                self::CONCEPT, WeakAreaClassification::WeakPersistent, useRealWeakAreaQuery: true,
                expected: new ExpectedReassessment(
                    expectEligible: true,
                    acceptableStatuses: [ReassessmentCandidateStatus::Validated],
                    expectConceptAlignment: true, expectCandidateContent: true,
                    rationale: 'A persistent weak area is eligible; the deterministic generator produces a task-aligned, validated candidate.',
                ),
            ),
            new ReassessmentScenario(
                'IEV-REASSESS-INSUFFICIENT-001', 'reassessment',
                'Insufficient-evidence classification is not eligible.',
                self::CONCEPT, WeakAreaClassification::InsufficientEvidence, useRealWeakAreaQuery: false,
                expected: new ExpectedReassessment(
                    expectEligible: false,
                    acceptableStatuses: [ReassessmentCandidateStatus::NotEligibleInsufficientEvidence],
                    rationale: 'Insufficient evidence is not eligible for reassessment candidate generation.',
                ),
            ),
            new ReassessmentScenario(
                'IEV-REASSESS-RECOVERED-001', 'reassessment',
                'No-current-weakness classification is not eligible (recovered).',
                self::CONCEPT, WeakAreaClassification::NoCurrentWeakness, useRealWeakAreaQuery: false,
                expected: new ExpectedReassessment(
                    expectEligible: false,
                    acceptableStatuses: [ReassessmentCandidateStatus::NotEligibleRecovered],
                    rationale: 'A recovered learning area does not require reassessment.',
                ),
            ),
            new ReassessmentScenario(
                'IEV-REASSESS-UNRESOLVED-REVIEW-001', 'reassessment',
                'Weak-unresolved is structurally eligible but pedagogical quality needs human review.',
                self::CONCEPT, WeakAreaClassification::WeakUnresolved, useRealWeakAreaQuery: false,
                expected: new ExpectedReassessment(
                    expectEligible: true,
                    acceptableStatuses: [
                        ReassessmentCandidateStatus::Validated,
                        ReassessmentCandidateStatus::Generated,
                        ReassessmentCandidateStatus::ValidationFailed,
                    ],
                    expectConceptAlignment: true,
                    ambiguous: true,
                    rationale: 'Structure/constraints are checkable automatically, but pedagogical appropriateness of the prompt requires expert REVIEW.',
                ),
            ),
        ];
    }

    /**
     * @return list<InterventionEvaluationScenario>
     */
    private function responseScenarios(): array
    {
        return [
            new ResponseScenario(
                'IEV-RESP-POSITIVE-001', 'response',
                'needs_support → progressing with an accepted retry is observed improvement.',
                LearningStateValue::NeedsSupport, LearningStateValue::Progressing, 'submission_accepted', remedial: true,
                afterCognitive: 'successful_task_outcome_observed',
                expected: new ExpectedResponse(
                    classification: InterventionResponseClassification::PositiveResponse,
                    improvementSignal: ObservedImprovementSignal::ObservedImprovement,
                    observedImprovement: true,
                    rationale: 'A positive state transition after intervention is an observed improvement (not a causal claim).',
                ),
            ),
            new ResponseScenario(
                'IEV-RESP-PERSISTENT-001', 'response',
                'needs_support → needs_support with continued failure is persistent difficulty.',
                LearningStateValue::NeedsSupport, LearningStateValue::NeedsSupport, 'submission_rejected', remedial: true,
                afterCognitive: null,
                expected: new ExpectedResponse(
                    classification: InterventionResponseClassification::NegativeOrPersistentDifficulty,
                    improvementSignal: ObservedImprovementSignal::NoObservedImprovement,
                    observedImprovement: false,
                    rationale: 'Persistent needs_support with failure evidence is no observed improvement.',
                ),
            ),
            new ResponseScenario(
                'IEV-RESP-INSUFFICIENT-001', 'boundary',
                'No post-intervention evidence or state is inconclusive.',
                LearningStateValue::NeedsSupport, null, null, remedial: true,
                afterCognitive: null,
                expected: new ExpectedResponse(
                    classification: InterventionResponseClassification::InsufficientEvidence,
                    improvementSignal: ObservedImprovementSignal::Inconclusive,
                    observedImprovement: false,
                    rationale: 'With no post-intervention data the result is inconclusive, not a claim of ineffectiveness.',
                ),
            ),
        ];
    }
}
