<?php

namespace App\Services\Research\Reassessment;

use App\Contracts\Research\ReassessmentCandidateGenerator;
use App\Exceptions\ReassessmentGenerationException;

/**
 * Deterministic non-LLM candidate generator for environments without AI credentials.
 *
 * Produces a different wording/scenario from the source activity while preserving
 * concept and task demands from the rule-based specification.
 */
final class DeterministicReassessmentCandidateGenerator implements ReassessmentCandidateGenerator
{
    public function generate(array $specification): array
    {
        $concept = (string) ($specification['concept'] ?? '');
        $bloom = (string) ($specification['bloom_demand'] ?? '');
        $dave = $specification['dave_demand'] ?? null;
        $objective = $specification['learning_objective'] ?? null;

        if ($concept === '' || $bloom === '') {
            throw new ReassessmentGenerationException(
                'Specification missing required concept or bloom_demand for candidate generation.',
                'malformed_specification',
            );
        }

        $scenario = sprintf(
            'A learner must solve a new problem about %s that is different from prior practice items, while still requiring the same task demand.',
            $concept,
        );

        $taskPrompt = sprintf(
            'Create a short programming exercise that requires applying %s in a new scenario. Do not reuse the previous activity wording. Focus on the intended learning objective%s.',
            $concept,
            is_string($objective) && $objective !== '' ? ' ('.$objective.')' : '',
        );

        return [
            'title' => 'Reassessment: '.$concept,
            'task_prompt' => $taskPrompt,
            'scenario' => $scenario,
            'concept' => $concept,
            'learning_objective' => is_string($objective) ? $objective : null,
            'bloom_demand' => $bloom,
            'dave_demand' => is_string($dave) ? $dave : null,
            'task_format' => (string) ($specification['constraints']['task_format'] ?? 'coding_exercise'),
            'expected_outcome' => 'Learner produces a working solution demonstrating '.$concept.' at the specified task demand without copying the prior activity.',
            'rubric' => 'Score based on correct use of '.$concept.', alignment with Bloom '.$bloom.' task demand, and completeness of the solution process. Do not award credit for cosmetic renames of a previous solution.',
            'includes_direct_answer' => false,
            'generator_identity' => 'deterministic_reassessment_candidate_generator',
            'generator_model' => 'template-v1',
            'metadata' => [
                'ai_assisted' => false,
                'llm_decision_maker' => false,
                'varies_scenario' => true,
            ],
        ];
    }
}
