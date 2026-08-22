<?php

namespace App\Services\Evaluation\Integrated;

use App\Enums\LearningStateValue;
use App\Enums\NextLearningActionType;
use App\Services\Evaluation\EvaluationStatus;

/**
 * Deterministic cross-layer comparison for integrated NEXUS validation (M6-07).
 *
 * Enforces the universal cross-layer invariants (evidence↔state, state↔intervention,
 * ↔next-action linkage, provenance completeness, task-demand consistency, and no
 * causal claim) plus the scenario's authored terminal/retry/reassessment/failure
 * expectations. Reads only the two objects; never invokes a production component.
 */
final class IntegrationComparator
{
    /**
     * @return array{status: EvaluationStatus, differences: list<string>, dimensions: array<string, string>}
     */
    public function compare(ExpectedIntegration $expected, ActualIntegration $actual): array
    {
        $differences = [];
        $dimensions = [];

        // Universal cross-layer invariants (always enforced).
        $dimensions['evidence_state_linkage'] = $this->boolDim('evidence↔state linkage', $actual->evidenceMatchesState, $differences);

        if ($actual->interventionLinksState !== null) {
            $dimensions['state_intervention_linkage'] = $this->boolDim('state↔intervention linkage', $actual->interventionLinksState, $differences);
        }

        $dimensions['next_action_linkage'] = $this->boolDim('state↔next_action linkage', $actual->nextActionLinksState, $differences);
        $dimensions['provenance_completeness'] = $this->boolDim('provenance completeness', $actual->provenanceComplete, $differences);
        $dimensions['task_demand_consistency'] = $this->boolDim('Bloom/Dave task-demand consistency', $actual->taskDemandConsistent, $differences);

        if ($actual->claimsCausal) {
            $dimensions['no_causal_claim'] = 'fail';
            $differences[] = 'integration asserted an unsupported causal claim';
        } else {
            $dimensions['no_causal_claim'] = 'pass';
        }

        // Terminal state / next action.
        if ($expected->terminalState !== null) {
            $dimensions['terminal_state'] = $this->matchDim(
                'terminal_state', $expected->terminalState->value, $actual->terminalState,
                array_map(fn (LearningStateValue $s): string => $s->value, $expected->acceptableTerminalStates), $differences,
            );
        }

        if ($expected->nextAction !== null) {
            $dimensions['terminal_next_action'] = $this->matchDim(
                'terminal_next_action', $expected->nextAction->value, $actual->terminalNextAction,
                array_map(fn (NextLearningActionType $a): string => $a->value, $expected->acceptableNextActions), $differences,
            );
        }

        // Intervention presence.
        if ($expected->expectIntervention !== null) {
            $dimensions['intervention_presence'] = $expected->expectIntervention === $actual->interventionPresent ? 'pass' : 'fail';
            if ($expected->expectIntervention !== $actual->interventionPresent) {
                $differences[] = 'intervention presence mismatch: expected '.($expected->expectIntervention ? 'present' : 'absent').', actual '.($actual->interventionPresent ? 'present' : 'absent');
            }
        }

        // Closed-loop retry integrity.
        if ($expected->expectRetryConsumesSameIntervention) {
            $dimensions['retry_closed_loop_integrity'] = $actual->retryConsumesSameIntervention === true ? 'pass' : 'fail';
            if ($actual->retryConsumesSameIntervention !== true) {
                $differences[] = 'closed-loop integrity: retry pass did not consume the same prior intervention';
            }
        }

        // Reassessment flow integrity.
        if ($expected->expectReassessmentEligible !== null) {
            $eligible = (bool) ($actual->reassessment['eligible'] ?? false);
            $provOk = (bool) ($actual->reassessment['provenance_consistent'] ?? false);
            $ok = $eligible === $expected->expectReassessmentEligible && ($expected->expectReassessmentEligible ? $provOk : true);
            $dimensions['reassessment_flow'] = $ok ? 'pass' : 'fail';
            if (! $ok) {
                $differences[] = 'reassessment flow integrity failed (eligibility or provenance linkage)';
            }
        }

        if ($expected->expectReassessmentSourceOfTruthUnchanged) {
            $unchanged = (bool) ($actual->reassessment['source_of_truth_unchanged'] ?? false);
            $dimensions['reassessment_source_of_truth'] = $unchanged ? 'pass' : 'fail';
            if (! $unchanged) {
                $differences[] = 'reassessment mutated durable source-of-truth records';
            }
        }

        // Failure propagation.
        if ($expected->expectGracefulFailure) {
            $status = $actual->reassessment['status'] ?? null;
            $ok = $status === ($expected->expectedFailureStatus ?? 'generation_failed');
            $dimensions['failure_propagation'] = $ok ? 'pass' : 'fail';
            if (! $ok) {
                $differences[] = 'failure propagation: expected status "'.($expected->expectedFailureStatus ?? 'generation_failed').'", actual "'.($status ?? 'none').'"';
            }
        }

        if ($expected->expectUpstreamIntactAfterFailure) {
            $intact = (bool) ($actual->reassessment['upstream_intact'] ?? false);
            $dimensions['upstream_intact_after_failure'] = $intact ? 'pass' : 'fail';
            if (! $intact) {
                $differences[] = 'upstream loop outputs were not intact after a downstream failure';
            }
        }

        $dimensions['determinism'] = $actual->deterministic ? 'pass' : 'fail';
        if (! $actual->deterministic) {
            $differences[] = 'determinism: repeated end-to-end execution produced a different cycle identity';
        }

        $status = $this->deriveStatus($expected, $dimensions);

        return ['status' => $status, 'differences' => array_values($differences), 'dimensions' => $dimensions];
    }

    /**
     * @param  list<string>  $differences
     */
    private function boolDim(string $label, bool $value, array &$differences): string
    {
        if ($value) {
            return 'pass';
        }
        $differences[] = $label.' failed';

        return 'fail';
    }

    /**
     * @param  list<string>  $acceptable
     * @param  list<string>  $differences
     */
    private function matchDim(string $label, string $expected, string $actual, array $acceptable, array &$differences): string
    {
        if ($actual === $expected) {
            return 'pass';
        }
        if (in_array($actual, $acceptable, true)) {
            $differences[] = $label.' "'.$actual.'" within acceptable set but not primary "'.$expected.'"';

            return 'review';
        }
        $differences[] = $label.' mismatch: expected "'.$expected.'", actual "'.$actual.'"';

        return 'fail';
    }

    /**
     * @param  array<string, string>  $dimensions
     */
    private function deriveStatus(ExpectedIntegration $expected, array $dimensions): EvaluationStatus
    {
        if (in_array('fail', $dimensions, true)) {
            return EvaluationStatus::Fail;
        }

        if ($expected->ambiguous || in_array('review', $dimensions, true)) {
            return EvaluationStatus::Review;
        }

        return EvaluationStatus::Pass;
    }
}
