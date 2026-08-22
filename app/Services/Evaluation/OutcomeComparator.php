<?php

namespace App\Services\Evaluation;

use App\Enums\LearningStateValue;
use App\Enums\NextLearningActionType;

/**
 * Deterministic comparison of an independently authored ExpectedOutcome against
 * the Actual NEXUS outcome (M6-01).
 *
 * The comparator only reads the two outcome objects. It never calls a production
 * rule, so it cannot smuggle the implementation-under-test into the Expected side.
 * Given the same inputs it always yields the same status, differences, and dimensions.
 */
final class OutcomeComparator
{
    /**
     * @return array{
     *     status: EvaluationStatus,
     *     differences: list<string>,
     *     dimensions: array<string, string>
     * }
     */
    public function compare(EvaluationScenario $scenario, ExpectedOutcome $expected, ActualOutcome $actual): array
    {
        $differences = [];
        $notes = [];

        $stateDim = $this->compareState($expected, $actual, $differences);
        $actionDim = $this->compareNextAction($expected, $actual, $differences);
        $interventionDim = $this->compareIntervention($expected, $actual, $differences);

        $correctness = $this->rollUp([$stateDim, $actionDim, $interventionDim]);
        $consistency = $this->consistency($actual, $differences);
        $boundaryHandling = $this->boundaryHandling($scenario, $actual);
        $failureHandling = 'pass';

        $dimensions = [
            'correctness' => $correctness,
            'consistency' => $consistency,
            'boundary_handling' => $boundaryHandling,
            'failure_handling' => $failureHandling,
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
    private function compareState(ExpectedOutcome $expected, ActualOutcome $actual, array &$differences): string
    {
        if ($actual->state === $expected->state->value) {
            return 'pass';
        }

        $acceptable = array_map(
            fn (LearningStateValue $s): string => $s->value,
            $expected->acceptableStates,
        );

        if (in_array($actual->state, $acceptable, true)) {
            $differences[] = sprintf(
                'learning_state actual "%s" is within the authored acceptable set but is not the primary expected "%s"',
                $actual->state,
                $expected->state->value,
            );

            return 'review';
        }

        $differences[] = sprintf(
            'learning_state mismatch: expected "%s", actual "%s"',
            $expected->state->value,
            $actual->state,
        );

        return 'fail';
    }

    /**
     * @param  list<string>  $differences
     */
    private function compareNextAction(ExpectedOutcome $expected, ActualOutcome $actual, array &$differences): string
    {
        if ($actual->nextAction === $expected->nextAction->value) {
            return 'pass';
        }

        $acceptable = array_map(
            fn (NextLearningActionType $a): string => $a->value,
            $expected->acceptableNextActions,
        );

        if (in_array($actual->nextAction, $acceptable, true)) {
            $differences[] = sprintf(
                'next_action actual "%s" is within the authored acceptable set but is not the primary expected "%s"',
                $actual->nextAction,
                $expected->nextAction->value,
            );

            return 'review';
        }

        $differences[] = sprintf(
            'next_action mismatch: expected "%s", actual "%s"',
            $expected->nextAction->value,
            $actual->nextAction,
        );

        return 'fail';
    }

    /**
     * @param  list<string>  $differences
     */
    private function compareIntervention(ExpectedOutcome $expected, ActualOutcome $actual, array &$differences): string
    {
        $dim = 'pass';

        if ($expected->expectRemedialIntervention && ! $actual->remedialInterventionCreated) {
            $differences[] = 'intervention mismatch: expected a remedial adaptive intervention, but none was created';
            $dim = 'fail';
        }

        if (! $expected->expectRemedialIntervention && $actual->remedialInterventionCreated) {
            $differences[] = 'intervention mismatch: expected no remedial adaptive intervention, but one was created';
            $dim = 'fail';
        }

        if ($expected->interventionType !== null
            && $actual->interventionType !== $expected->interventionType->value) {
            $differences[] = sprintf(
                'intervention_type mismatch: expected "%s", actual "%s"',
                $expected->interventionType->value,
                $actual->interventionType ?? 'none',
            );
            $dim = 'fail';
        }

        return $dim;
    }

    /**
     * @param  list<string>  $dims
     */
    private function rollUp(array $dims): string
    {
        if (in_array('fail', $dims, true)) {
            return 'fail';
        }

        if (in_array('review', $dims, true)) {
            return 'review';
        }

        return 'pass';
    }

    /**
     * Internal coherence of the actual outcome against the pipeline's own contract.
     *
     * @param  list<string>  $differences
     */
    private function consistency(ActualOutcome $actual, array &$differences): string
    {
        $coherent = match ($actual->state) {
            LearningStateValue::InsufficientEvidence->value => $actual->nextAction === NextLearningActionType::CollectMoreEvidence->value,
            LearningStateValue::Stable->value, LearningStateValue::Progressing->value => $actual->nextAction === NextLearningActionType::Continue->value,
            LearningStateValue::NeedsSupport->value => in_array($actual->nextAction, [
                NextLearningActionType::Continue->value,
                NextLearningActionType::GuidedRetry->value,
                NextLearningActionType::PracticeAgain->value,
                NextLearningActionType::ReviewConcept->value,
                NextLearningActionType::Reassessment->value,
            ], true),
            default => true,
        };

        if (! $coherent) {
            $differences[] = sprintf(
                'consistency: state "%s" paired with next_action "%s" is outside the expected coherent set',
                $actual->state,
                $actual->nextAction,
            );

            return 'review';
        }

        return 'pass';
    }

    private function boundaryHandling(EvaluationScenario $scenario, ActualOutcome $actual): string
    {
        if ($scenario->category !== 'boundary') {
            return 'not_applicable';
        }

        // A boundary scenario passes when the pipeline still returns a defined,
        // conservative state rather than erroring or fabricating certainty.
        $defined = LearningStateValue::tryFrom($actual->state) !== null
            && NextLearningActionType::tryFrom($actual->nextAction) !== null;

        return $defined ? 'pass' : 'fail';
    }

    /**
     * @param  array<string, string>  $dimensions
     * @param  list<string>  $differences
     */
    private function deriveStatus(
        ExpectedOutcome $expected,
        ActualOutcome $actual,
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
            $differences[] = 'flagged for review: state_confidence is low for a scenario that expects a decisive outcome';

            return EvaluationStatus::Review;
        }

        return EvaluationStatus::Pass;
    }
}
