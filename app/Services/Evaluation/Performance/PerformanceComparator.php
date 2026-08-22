<?php

namespace App\Services\Evaluation\Performance;

use App\Services\Evaluation\EvaluationStatus;

/**
 * Deterministic comparison of independently authored performance criteria against
 * the measured actual outcome (M6-06).
 *
 * Reads only the two objects; never invokes a production component. Raw
 * measurement scenarios resolve to REVIEW (no invented threshold); only objective
 * reliability behaviors resolve to PASS/FAIL.
 */
final class PerformanceComparator
{
    /**
     * @return array{status: EvaluationStatus, differences: list<string>, dimensions: array<string, string>}
     */
    public function compare(ExpectedPerformance $expected, ActualPerformance $actual): array
    {
        $differences = [];
        $dimensions = [];

        if ($expected->measurementOnly) {
            // Measured and reported; an evidence-based project baseline (human
            // judgment) is required before a pass/fail threshold can be applied.
            $dimensions['measurement'] = 'review';

            return ['status' => EvaluationStatus::Review, 'differences' => [], 'dimensions' => $dimensions];
        }

        if ($expected->expectDeterministic) {
            $dimensions['determinism'] = $actual->deterministic === true ? 'pass' : 'fail';
            if ($actual->deterministic !== true) {
                $differences[] = 'determinism: repeated execution did not produce identical logical output';
            }
        }

        if ($expected->expectGracefulFailure) {
            $ok = $actual->failureHandled === true;
            if (! $ok) {
                $differences[] = 'failure_handling: failure was not handled gracefully';
            }

            if ($expected->expectedFailureStatus !== null && $actual->failureStatus !== $expected->expectedFailureStatus) {
                $ok = false;
                $differences[] = sprintf('failure_handling: expected status "%s", actual "%s"', $expected->expectedFailureStatus, $actual->failureStatus ?? 'none');
            }

            $dimensions['failure_handling'] = $ok ? 'pass' : 'fail';
        }

        if ($expected->expectSourceOfTruthUnchanged) {
            $dimensions['source_of_truth_unchanged'] = $actual->sourceOfTruthUnchanged === true ? 'pass' : 'fail';
            if ($actual->sourceOfTruthUnchanged !== true) {
                $differences[] = 'source_of_truth_unchanged: a durable record was mutated during failure handling';
            }
        }

        if ($expected->expectAiAbstractionHonored) {
            $honored = $expected->expectedGeneratorIdentity === null
                ? $actual->aiGeneratorIdentity !== null
                : $actual->aiGeneratorIdentity === $expected->expectedGeneratorIdentity;
            $dimensions['ai_abstraction'] = $honored ? 'pass' : 'fail';
            if (! $honored) {
                $differences[] = sprintf('ai_abstraction: expected generator "%s", actual "%s"', $expected->expectedGeneratorIdentity ?? '(any)', $actual->aiGeneratorIdentity ?? 'none');
            }
        }

        if ($expected->expectAiNotDecisionMaker) {
            $dimensions['ai_not_decision_maker'] = $actual->aiIsDecisionMaker === false ? 'pass' : 'fail';
            if ($actual->aiIsDecisionMaker !== false) {
                $differences[] = 'ai_not_decision_maker: AI was reported as a final decision-maker';
            }
        }

        $status = $this->deriveStatus($expected, $dimensions);

        return ['status' => $status, 'differences' => array_values($differences), 'dimensions' => $dimensions];
    }

    /**
     * @param  array<string, string>  $dimensions
     */
    private function deriveStatus(ExpectedPerformance $expected, array $dimensions): EvaluationStatus
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
