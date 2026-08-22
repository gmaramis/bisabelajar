<?php

namespace App\Services\Evaluation\CognitiveAffective;

use App\Enums\LearningStateValue;
use App\Services\Evaluation\EvaluationStatus;

/**
 * Deterministic comparison of independently authored expected indicators against
 * the actual M4-T03 observable indicators (M6-04).
 *
 * Reads only the two outcome objects; never invokes a production rule. Given
 * identical inputs it always yields identical status/differences/dimensions.
 */
final class IndicatorComparator
{
    /**
     * @return array{status: EvaluationStatus, differences: list<string>, dimensions: array<string, string>}
     */
    public function compare(ExpectedIndicators $expected, ActualIndicators $actual): array
    {
        $differences = [];

        $dimensions = [
            'cognitive_interpretation' => $this->compareIndicator('cognitive_indicator', $expected->cognitiveIndicator, $expected->expectCognitiveNull, $actual->cognitiveIndicator, $differences),
            'psychomotor_interpretation' => $this->compareIndicator('psychomotor_indicator', $expected->psychomotorIndicator, $expected->expectPsychomotorNull, $actual->psychomotorIndicator, $differences),
            'behavioral_interpretation' => $this->compareBehavioral($expected, $actual, $differences),
            'state_consistency' => $this->compareState($expected, $actual, $differences),
        ];

        $status = $this->deriveStatus($expected, $dimensions);

        return ['status' => $status, 'differences' => array_values($differences), 'dimensions' => $dimensions];
    }

    /**
     * @param  list<string>  $differences
     */
    private function compareIndicator(string $name, ?string $expected, bool $expectNull, ?string $actual, array &$differences): string
    {
        if ($expectNull) {
            if ($actual === null) {
                return 'pass';
            }
            $differences[] = $name.' expected to be absent (null) but was "'.$actual.'"';

            return 'fail';
        }

        if ($expected === null) {
            return 'not_applicable';
        }

        if ($expected === $actual) {
            return 'pass';
        }

        $differences[] = $name.' mismatch: expected "'.$expected.'", actual "'.($actual ?? 'none').'"';

        return 'fail';
    }

    /**
     * @param  list<string>  $differences
     */
    private function compareBehavioral(ExpectedIndicators $expected, ActualIndicators $actual, array &$differences): string
    {
        if ($expected->expectBehavioralEmpty) {
            if ($actual->behavioralIndicators === []) {
                return 'pass';
            }
            $differences[] = 'behavioral indicators expected to be empty but were ['.implode(', ', $actual->behavioralIndicators).']';

            return 'fail';
        }

        $dim = 'not_applicable';

        if ($expected->requiredBehavioral !== []) {
            $missing = array_values(array_diff($expected->requiredBehavioral, $actual->behavioralIndicators));
            if ($missing === []) {
                $dim = 'pass';
            } else {
                $differences[] = 'behavioral indicators missing: '.implode(', ', $missing);

                return 'fail';
            }
        }

        if ($expected->forbiddenBehavioral !== []) {
            $present = array_values(array_intersect($expected->forbiddenBehavioral, $actual->behavioralIndicators));
            if ($present !== []) {
                $differences[] = 'forbidden behavioral indicators present: '.implode(', ', $present);

                return 'fail';
            }
            $dim = $dim === 'not_applicable' ? 'pass' : $dim;
        }

        return $dim;
    }

    /**
     * @param  list<string>  $differences
     */
    private function compareState(ExpectedIndicators $expected, ActualIndicators $actual, array &$differences): string
    {
        if ($expected->expectedState === null) {
            return 'not_applicable';
        }

        if ($actual->state === $expected->expectedState->value) {
            return 'pass';
        }

        $acceptable = array_map(fn (LearningStateValue $s): string => $s->value, $expected->acceptableStates);
        if (in_array($actual->state, $acceptable, true)) {
            $differences[] = 'state "'.$actual->state.'" within acceptable set but not primary "'.$expected->expectedState->value.'"';

            return 'review';
        }

        $differences[] = 'state mismatch: expected "'.$expected->expectedState->value.'", actual "'.$actual->state.'"';

        return 'fail';
    }

    /**
     * @param  array<string, string>  $dimensions
     */
    private function deriveStatus(ExpectedIndicators $expected, array $dimensions): EvaluationStatus
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
