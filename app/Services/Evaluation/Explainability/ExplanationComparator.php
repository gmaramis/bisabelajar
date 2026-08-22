<?php

namespace App\Services\Evaluation\Explainability;

use App\Services\Evaluation\EvaluationStatus;

/**
 * Deterministic comparison of an independently authored ExpectedExplanation
 * against the normalized ActualExplanation captured from a NEXUS component (M6-05).
 *
 * Reads only the two outcome objects; never invokes a production component. Given
 * identical inputs it always yields identical status/differences/dimensions.
 */
final class ExplanationComparator
{
    /**
     * @return array{status: EvaluationStatus, differences: list<string>, dimensions: array<string, string>}
     */
    public function compare(ExpectedExplanation $expected, ActualExplanation $actual): array
    {
        $differences = [];
        $dimensions = [];

        // Transparency: reason and rule present where required.
        $transparency = 'pass';
        if ($expected->requireReason && trim($actual->explanationText) === '') {
            $transparency = 'fail';
            $differences[] = 'transparency: required explanation/reason is missing';
        }
        if ($expected->requireRule && ($actual->rule === null || trim((string) $actual->rule) === '')) {
            $transparency = 'fail';
            $differences[] = 'transparency: required decision/inference rule is missing';
        }
        $dimensions['transparency'] = $transparency;

        // Explanation content expectations (also used to author divergence).
        if ($expected->explanationMustContain !== []) {
            $missing = [];
            foreach ($expected->explanationMustContain as $needle) {
                if (! str_contains($actual->explanationText, $needle)) {
                    $missing[] = $needle;
                }
            }
            if ($missing === []) {
                $dimensions['explanation_content'] = 'pass';
            } else {
                $dimensions['explanation_content'] = 'fail';
                $differences[] = 'explanation missing required content: '.implode(' | ', $missing);
            }
        }

        // Provenance completeness.
        if ($expected->requireProvenance) {
            $dimensions['provenance_completeness'] = $actual->hasProvenance ? 'pass' : 'fail';
            if (! $actual->hasProvenance) {
                $differences[] = 'provenance_completeness: required provenance is absent or not followable';
            }
        }

        // Uncertainty visibility.
        if ($expected->requireConfidenceVisible) {
            $dimensions['uncertainty_visibility'] = $actual->confidenceVisible ? 'pass' : 'fail';
            if (! $actual->confidenceVisible) {
                $differences[] = 'uncertainty_visibility: required confidence/uncertainty is not surfaced';
            }
        }

        // Task-demand wording (Bloom/Dave remain task demand).
        if ($expected->requireTaskDemand) {
            $dimensions['task_demand_wording'] = $actual->bloomDaveTaskDemand ? 'pass' : 'fail';
            if (! $actual->bloomDaveTaskDemand) {
                $differences[] = 'task_demand_wording: Bloom/Dave not represented as task demand';
            }
        }

        $status = $this->deriveStatus($expected, $dimensions);

        return ['status' => $status, 'differences' => array_values($differences), 'dimensions' => $dimensions];
    }

    /**
     * @param  array<string, string>  $dimensions
     */
    private function deriveStatus(ExpectedExplanation $expected, array $dimensions): EvaluationStatus
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
