<?php

namespace App\Services\Evaluation\Integrated;

use App\Enums\LearningStateValue;
use App\Enums\NextLearningActionType;

/**
 * Independently authored library of synthetic end-to-end integrated NEXUS
 * scenarios covering the nine M6-07 paths plus a divergence self-test.
 *
 * INDEPENDENCE BOUNDARY: depends only on domain enums and the evaluation value
 * objects; it does not reference or execute any production component. Every
 * expectation is hand-authored from the M6-07 specification and the documented
 * cross-layer contract. Data is synthetic.
 */
final class IntegrationScenarioLibrary
{
    private const CONCEPT = 'loops';

    /**
     * @return list<IntegrationScenario>
     */
    public function all(): array
    {
        $scenarios = [
            $this->successPath(),
            $this->repeatedDifficultyPath(),
            $this->recoveryPath(),
            $this->insufficientPath(),
            $this->failedRetryPath(),
            $this->reassessmentPath(),
            $this->contextualVariationPath(),
            $this->privacyProvenancePath(),
            $this->errorFailurePath(),
            $this->divergencePath(),
        ];

        usort($scenarios, fn (IntegrationScenario $a, IntegrationScenario $b): int => strcmp($a->scenarioId, $b->scenarioId));

        return $scenarios;
    }

    private function make(
        string $id,
        string $path,
        string $description,
        array $initialEvents,
        bool $runRetry,
        array $retryEvents,
        ?string $retryOutcome,
        bool $runReassessment,
        bool $runContextualVariation,
        bool $injectGeneratorFailure,
        ExpectedIntegration $expected,
    ): IntegrationScenario {
        return new IntegrationScenario(
            $id, $path, $description, self::CONCEPT,
            $initialEvents, $runRetry, $retryEvents, $retryOutcome,
            $runReassessment, $runContextualVariation, $injectGeneratorFailure, $expected,
        );
    }

    private function successPath(): IntegrationScenario
    {
        return $this->make(
            'INT-SUCCESS-PATH-001', 'success',
            'Accepted submission flows to a stable state and continue with no intervention.',
            ['submission_accepted'], false, [], null, false, false, false,
            new ExpectedIntegration(
                terminalState: LearningStateValue::Stable,
                nextAction: NextLearningActionType::Continue,
                expectIntervention: false,
                rationale: 'A successful path must be stable/continue with a complete, consistent provenance chain and no intervention.',
            ),
        );
    }

    private function repeatedDifficultyPath(): IntegrationScenario
    {
        return $this->make(
            'INT-REPEATED-DIFFICULTY-PATH-001', 'repeated_difficulty',
            'Repeated rejections flow to needs_support, a remedial intervention, and a guided retry.',
            ['submission_rejected', 'submission_rejected'], false, [], null, false, false, false,
            new ExpectedIntegration(
                terminalState: LearningStateValue::NeedsSupport,
                nextAction: NextLearningActionType::GuidedRetry,
                acceptableNextActions: [NextLearningActionType::GuidedRetry, NextLearningActionType::PracticeAgain],
                expectIntervention: true,
                rationale: 'Repeated difficulty must link needs_support → remedial intervention → guided-retry decision.',
            ),
        );
    }

    private function recoveryPath(): IntegrationScenario
    {
        return $this->make(
            'INT-RECOVERY-PATH-001', 'recovery',
            'After intervention, an accepted retry flows to progressing/continue reusing the same intervention.',
            ['submission_rejected', 'submission_rejected'], true, ['submission_accepted'], 'success', false, false, false,
            new ExpectedIntegration(
                terminalState: LearningStateValue::Progressing,
                nextAction: NextLearningActionType::Continue,
                expectIntervention: true,
                expectRetryConsumesSameIntervention: true,
                rationale: 'Recovery must reuse the prior intervention and re-infer a progressing state from new evidence.',
            ),
        );
    }

    private function insufficientPath(): IntegrationScenario
    {
        return $this->make(
            'INT-INSUFFICIENT-PATH-001', 'insufficient',
            'No evidence flows to insufficient_evidence and collect_more_evidence, no intervention.',
            [], false, [], null, false, false, false,
            new ExpectedIntegration(
                terminalState: LearningStateValue::InsufficientEvidence,
                nextAction: NextLearningActionType::CollectMoreEvidence,
                expectIntervention: false,
                rationale: 'With no evidence the chain must remain consistent and collect more evidence.',
            ),
        );
    }

    private function failedRetryPath(): IntegrationScenario
    {
        return $this->make(
            'INT-FAILED-RETRY-PATH-001', 'failed_retry',
            'After intervention, a failed retry remains needs_support reusing the same intervention.',
            ['submission_rejected', 'submission_rejected'], true, ['submission_rejected'], 'failure', false, false, false,
            new ExpectedIntegration(
                terminalState: LearningStateValue::NeedsSupport,
                nextAction: NextLearningActionType::Reassessment,
                acceptableNextActions: [
                    NextLearningActionType::PracticeAgain,
                    NextLearningActionType::ReviewConcept,
                    NextLearningActionType::GuidedRetry,
                    NextLearningActionType::Reassessment,
                ],
                expectIntervention: true,
                expectRetryConsumesSameIntervention: true,
                rationale: 'A persistent weak area after a failed retry must stay needs_support, reuse the prior intervention, and route to reassessment (M4-T05 rule).',
            ),
        );
    }

    private function reassessmentPath(): IntegrationScenario
    {
        return $this->make(
            'INT-REASSESSMENT-PATH-001', 'reassessment',
            'Persistent difficulty yields an eligible reassessment candidate whose provenance links the learner and leaves source of truth unchanged.',
            ['submission_rejected', 'submission_rejected'], false, [], null, true, false, false,
            new ExpectedIntegration(
                terminalState: LearningStateValue::NeedsSupport,
                expectIntervention: true,
                expectReassessmentEligible: true,
                expectReassessmentSourceOfTruthUnchanged: true,
                rationale: 'Reassessment must consume upstream weak-area evidence with consistent provenance and no source-of-truth mutation.',
            ),
        );
    }

    private function contextualVariationPath(): IntegrationScenario
    {
        return $this->make(
            'INT-CONTEXTUAL-VARIATION-PATH-001', 'contextual_variation',
            'Contextual variation consumes the produced states descriptively; interpretation needs expert review.',
            ['submission_rejected', 'submission_rejected'], false, [], null, false, true, false,
            new ExpectedIntegration(
                terminalState: LearningStateValue::NeedsSupport,
                expectIntervention: true,
                ambiguous: true,
                rationale: 'Structural consumption is checkable, but cross-context interpretation is a human-judgment boundary → REVIEW.',
            ),
        );
    }

    private function privacyProvenancePath(): IntegrationScenario
    {
        return $this->make(
            'INT-PRIVACY-PROVENANCE-PATH-001', 'privacy_provenance',
            'A learner with sentinel PII flows through the loop with a complete provenance chain and no PII leakage.',
            ['submission_rejected', 'submission_rejected'], false, [], null, false, false, false,
            new ExpectedIntegration(
                terminalState: LearningStateValue::NeedsSupport,
                expectIntervention: true,
                rationale: 'The integrated output must be fully traceable and contain no learner PII.',
            ),
        );
    }

    private function errorFailurePath(): IntegrationScenario
    {
        return $this->make(
            'INT-ERROR-FAILURE-PATH-001', 'error_failure',
            'A downstream AI generation failure degrades gracefully while upstream loop outputs stay intact.',
            ['submission_rejected', 'submission_rejected'], false, [], null, true, false, true,
            new ExpectedIntegration(
                terminalState: LearningStateValue::NeedsSupport,
                expectIntervention: true,
                expectGracefulFailure: true,
                expectedFailureStatus: 'generation_failed',
                expectUpstreamIntactAfterFailure: true,
                expectReassessmentSourceOfTruthUnchanged: true,
                rationale: 'A downstream failure must not corrupt the upstream chain or mutate source of truth.',
            ),
        );
    }

    private function divergencePath(): IntegrationScenario
    {
        return $this->make(
            'INT-DIVERGENCE-FAIL-001', 'divergence',
            'A successful path, but the authored expectation asserts an intervention must exist.',
            ['submission_accepted'], false, [], null, false, false, false,
            new ExpectedIntegration(
                terminalState: LearningStateValue::Stable,
                nextAction: NextLearningActionType::Continue,
                expectIntervention: true,
                rationale: 'Intentionally divergent intervention-presence expectation used to prove FAIL detection.',
            ),
        );
    }
}
