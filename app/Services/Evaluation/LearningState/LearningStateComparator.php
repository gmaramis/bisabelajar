<?php

namespace App\Services\Evaluation\LearningState;

use App\Enums\LearningStateValue;
use App\Enums\StateConfidence;
use App\Services\Evaluation\EvaluationStatus;

/**
 * Deterministic comparison of an independently authored ExpectedLearningState
 * against the Actual M4-T03 outcome (M6-02).
 *
 * Reads only the two outcome objects; never invokes a production rule, so the
 * Expected side can never be contaminated by the implementation under validation.
 * Given identical inputs it always yields identical status/differences/dimensions.
 */
final class LearningStateComparator
{
    /**
     * @return array{
     *     status: EvaluationStatus,
     *     differences: list<string>,
     *     dimensions: array<string, string>
     * }
     */
    public function compare(ExpectedLearningState $expected, ActualLearningState $actual): array
    {
        $differences = [];

        $dimensions = [
            'state_classification' => $this->classifyState($expected, $actual, $differences),
            'state_confidence' => $this->classifyConfidence($expected, $actual, $differences),
            'cognitive_indicator' => $this->classifyIndicator('cognitive_indicator', $expected->cognitiveIndicator, $actual->cognitiveIndicator, $differences),
            'psychomotor_indicator' => $this->classifyIndicator('psychomotor_indicator', $expected->psychomotorIndicator, $actual->psychomotorIndicator, $differences),
            'behavioral_indicator' => $this->classifyBehavioral($expected, $actual, $differences),
            'explanation' => $this->classifyExplanation($expected, $actual, $differences),
            'evidence_quality' => $this->classifyEvidenceQuality($expected, $actual, $differences),
        ];

        $status = $this->deriveStatus($expected, $actual, $dimensions, $differences);

        return [
            'status' => $status,
            'differences' => array_values($differences),
            'dimensions' => $dimensions,
        ];
    }

    /**
     * @param  list<string>  $differences
     */
    private function classifyState(ExpectedLearningState $expected, ActualLearningState $actual, array &$differences): string
    {
        $dim = 'pass';

        if ($actual->state !== $expected->state->value) {
            $acceptable = array_map(fn (LearningStateValue $s): string => $s->value, $expected->acceptableStates);

            if (in_array($actual->state, $acceptable, true)) {
                $differences[] = sprintf(
                    'state actual "%s" is within the authored acceptable set but is not the primary expected "%s"',
                    $actual->state,
                    $expected->state->value,
                );
                $dim = 'review';
            } else {
                $differences[] = sprintf('state mismatch: expected "%s", actual "%s"', $expected->state->value, $actual->state);

                return 'fail';
            }
        }

        if ($expected->inferenceRule !== null && $actual->inferenceRule !== $expected->inferenceRule) {
            $differences[] = sprintf(
                'inference_rule mismatch: expected "%s", actual "%s"',
                $expected->inferenceRule,
                $actual->inferenceRule,
            );

            return 'fail';
        }

        return $dim;
    }

    /**
     * @param  list<string>  $differences
     */
    private function classifyConfidence(ExpectedLearningState $expected, ActualLearningState $actual, array &$differences): string
    {
        if ($expected->acceptableConfidences === []) {
            return 'not_applicable';
        }

        $acceptable = array_map(fn (StateConfidence $c): string => $c->value, $expected->acceptableConfidences);

        if (in_array($actual->stateConfidence, $acceptable, true)) {
            return 'pass';
        }

        $differences[] = sprintf(
            'state_confidence "%s" is outside the authored acceptable set [%s]',
            $actual->stateConfidence,
            implode(', ', $acceptable),
        );

        return 'review';
    }

    /**
     * @param  list<string>  $differences
     */
    private function classifyIndicator(string $name, ?string $expected, ?string $actual, array &$differences): string
    {
        if ($expected === null) {
            return 'not_applicable';
        }

        if ($expected === $actual) {
            return 'pass';
        }

        $differences[] = sprintf('%s mismatch: expected "%s", actual "%s"', $name, $expected, $actual ?? 'none');

        return 'fail';
    }

    /**
     * @param  list<string>  $differences
     */
    private function classifyBehavioral(ExpectedLearningState $expected, ActualLearningState $actual, array &$differences): string
    {
        if ($expected->requiredBehavioralIndicators === []) {
            return 'not_applicable';
        }

        $missing = array_values(array_diff($expected->requiredBehavioralIndicators, $actual->behavioralIndicators));

        if ($missing === []) {
            return 'pass';
        }

        $differences[] = 'behavioral indicators missing: '.implode(', ', $missing);

        return 'fail';
    }

    /**
     * @param  list<string>  $differences
     */
    private function classifyExplanation(ExpectedLearningState $expected, ActualLearningState $actual, array &$differences): string
    {
        if ($expected->explanationContains === []) {
            return 'not_applicable';
        }

        $missing = [];
        foreach ($expected->explanationContains as $needle) {
            if (! str_contains($actual->explanation, $needle)) {
                $missing[] = $needle;
            }
        }

        if ($missing === []) {
            return 'pass';
        }

        $differences[] = 'explanation missing expected substrings: '.implode(' | ', $missing);

        return 'fail';
    }

    /**
     * @param  list<string>  $differences
     */
    private function classifyEvidenceQuality(ExpectedLearningState $expected, ActualLearningState $actual, array &$differences): string
    {
        $checks = [
            ['usable_count', $expected->expectedUsableCount, $actual->usableCount()],
            ['uncertain_count', $expected->expectedUncertainCount, $actual->uncertainCount()],
            ['context_dependent_count', $expected->expectedContextDependentCount, $actual->contextDependentCount()],
        ];

        $asserted = false;
        $dim = 'pass';

        foreach ($checks as [$label, $expectedValue, $actualValue]) {
            if ($expectedValue === null) {
                continue;
            }

            $asserted = true;

            if ($expectedValue !== $actualValue) {
                $differences[] = sprintf('%s mismatch: expected %d, actual %d', $label, $expectedValue, $actualValue);
                $dim = 'fail';
            }
        }

        return $asserted ? $dim : 'not_applicable';
    }

    /**
     * @param  array<string, string>  $dimensions
     * @param  list<string>  $differences
     */
    private function deriveStatus(
        ExpectedLearningState $expected,
        ActualLearningState $actual,
        array $dimensions,
        array &$differences,
    ): EvaluationStatus {
        if (in_array('fail', $dimensions, true)) {
            return EvaluationStatus::Fail;
        }

        if ($expected->ambiguous) {
            return EvaluationStatus::Review;
        }

        if (in_array('review', $dimensions, true)) {
            return EvaluationStatus::Review;
        }

        if ($expected->reviewWhenLowConfidence && $actual->stateConfidence === 'low') {
            $differences[] = 'flagged for review: state_confidence is low for a scenario that expects a decisive classification';

            return EvaluationStatus::Review;
        }

        return EvaluationStatus::Pass;
    }
}
