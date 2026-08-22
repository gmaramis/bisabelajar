<?php

namespace App\Services\Evaluation\Explainability;

use App\Enums\EvidenceCategory;
use App\Enums\EvidenceConfidence;
use App\Enums\EvidenceQuality;
use App\Services\Evaluation\LearningState\EvidenceSpec;

/**
 * Independently authored library of synthetic explainability/trustworthiness
 * scenarios spanning the seven NEXUS components named by the M6-05 spec.
 *
 * INDEPENDENCE BOUNDARY: depends only on domain enums and evaluation value
 * objects; it does not reference or execute any production component. Every
 * expected trustworthiness requirement is hand-authored from the M6-05
 * specification. Data is synthetic.
 */
final class ExplainabilityScenarioLibrary
{
    private const CONCEPT = 'loops';

    /**
     * @return list<ExplainabilityScenario>
     */
    public function all(): array
    {
        $scenarios = [
            $this->learningState(),
            $this->intervention(),
            $this->nextAction(),
            $this->weakArea(),
            $this->reassessment(),
            $this->response(),
            $this->contextualVariation(),
            $this->divergenceFail(),
            $this->understandabilityReview(),
        ];

        usort($scenarios, fn (ExplainabilityScenario $a, ExplainabilityScenario $b): int => strcmp($a->scenarioId, $b->scenarioId));

        return $scenarios;
    }

    /**
     * @return list<EvidenceSpec>
     */
    private function needsSupportEvidence(): array
    {
        return [
            new EvidenceSpec('submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::Medium),
            new EvidenceSpec('repeated_submission_failures', EvidenceCategory::Behavioral, EvidenceQuality::ContextDependent, EvidenceConfidence::Medium),
        ];
    }

    private function learningState(): ExplainabilityScenario
    {
        return new ExplainabilityScenario(
            'EXP-LEARNING-STATE-001', 'learning_state',
            'Learning State inference explanation is transparent, grounded, and bounded.',
            self::CONCEPT, $this->needsSupportEvidence(),
            new ExpectedExplanation(
                requireReason: true, requireRule: true, requireProvenance: true,
                requireTaskDemand: true, requireNoDiagnosis: true, requireNoCausalClaim: true, requireConfidenceVisible: true,
                rationale: 'T03 must expose an explanation, an inference rule, evidence provenance, task-demand wording, and state confidence.',
            ),
        );
    }

    private function intervention(): ExplainabilityScenario
    {
        return new ExplainabilityScenario(
            'EXP-INTERVENTION-001', 'intervention',
            'Adaptive intervention exposes a reason, selection rule, and provenance.',
            self::CONCEPT, $this->needsSupportEvidence(),
            new ExpectedExplanation(
                requireReason: true, requireRule: true, requireProvenance: true,
                requireTaskDemand: true, requireNoDiagnosis: true, requireNoCausalClaim: true, requireConfidenceVisible: true,
                rationale: 'T04 must expose a reason, a selection rule, provenance, and no diagnosis/causal claim.',
            ),
        );
    }

    private function nextAction(): ExplainabilityScenario
    {
        return new ExplainabilityScenario(
            'EXP-NEXT-ACTION-001', 'next_action',
            'Next learning action exposes a reason and decision rule.',
            self::CONCEPT, $this->needsSupportEvidence(),
            new ExpectedExplanation(
                requireReason: true, requireRule: true, requireProvenance: true,
                requireTaskDemand: true, requireNoDiagnosis: true, requireNoCausalClaim: true, requireConfidenceVisible: true,
                rationale: 'T05 must expose a reason, a decision rule, and provenance.',
            ),
        );
    }

    private function weakArea(): ExplainabilityScenario
    {
        return new ExplainabilityScenario(
            'EXP-WEAK-AREA-001', 'weak_area',
            'Weak-area finding exposes an explanation, detection rule, and provenance.',
            self::CONCEPT, [],
            new ExpectedExplanation(
                requireReason: true, requireRule: true, requireProvenance: true,
                requireTaskDemand: true, requireNoDiagnosis: true, requireNoCausalClaim: true,
                rationale: 'M5-03 must expose an explanation, a detection rule, provenance, and task-demand wording.',
            ),
        );
    }

    private function reassessment(): ExplainabilityScenario
    {
        return new ExplainabilityScenario(
            'EXP-REASSESSMENT-001', 'reassessment',
            'Reassessment candidate exposes a grounded specification and provenance without effectiveness claims.',
            self::CONCEPT, [],
            new ExpectedExplanation(
                requireReason: true, requireRule: false, requireProvenance: true,
                requireTaskDemand: true, requireNoDiagnosis: true, requireNoCausalClaim: true,
                rationale: 'M5-04 exposes a task-demand specification with provenance and no effectiveness/improvement claim; it has no single decision-rule string.',
            ),
        );
    }

    private function response(): ExplainabilityScenario
    {
        return new ExplainabilityScenario(
            'EXP-RESPONSE-001', 'response',
            'Observed intervention response exposes an explanation, comparison rule, and no causal claim.',
            self::CONCEPT, [],
            new ExpectedExplanation(
                requireReason: true, requireRule: true, requireProvenance: true,
                requireTaskDemand: true, requireNoDiagnosis: true, requireNoCausalClaim: true, requireConfidenceVisible: true,
                rationale: 'M5-05 must expose an observational explanation and comparison rule with provenance and no causal attribution.',
            ),
        );
    }

    private function contextualVariation(): ExplainabilityScenario
    {
        return new ExplainabilityScenario(
            'EXP-CONTEXTUAL-VARIATION-001', 'contextual_variation',
            'Contextual variation exposes a descriptive explanation and no causal claim.',
            self::CONCEPT, [],
            new ExpectedExplanation(
                requireReason: true, requireRule: false, requireProvenance: true,
                requireTaskDemand: false, requireNoDiagnosis: true, requireNoCausalClaim: true,
                rationale: 'M5-06 exposes a descriptive explanation with context provenance and never a context-caused-outcome claim.',
            ),
        );
    }

    private function divergenceFail(): ExplainabilityScenario
    {
        return new ExplainabilityScenario(
            'EXP-DIVERGENCE-FAIL-001', 'learning_state',
            'Learning State explanation, but the authored expectation demands a phrase it will never contain.',
            self::CONCEPT, $this->needsSupportEvidence(),
            new ExpectedExplanation(
                requireReason: true, requireRule: true, requireProvenance: true,
                explanationMustContain: ['__SENTINEL_PHRASE_THAT_WILL_NOT_APPEAR__'],
                rationale: 'Intentionally divergent content expectation used to prove FAIL detection.',
            ),
        );
    }

    private function understandabilityReview(): ExplainabilityScenario
    {
        return new ExplainabilityScenario(
            'EXP-UNDERSTANDABILITY-REVIEW-001', 'response',
            'Structurally valid response explanation whose pedagogical understandability needs expert review.',
            self::CONCEPT, [],
            new ExpectedExplanation(
                requireReason: true, requireRule: true, requireProvenance: true,
                requireTaskDemand: true, requireNoDiagnosis: true, requireNoCausalClaim: true,
                ambiguous: true,
                rationale: 'Whether the explanation is understandable and pedagogically meaningful is an expert-judgment boundary → REVIEW.',
            ),
        );
    }
}
