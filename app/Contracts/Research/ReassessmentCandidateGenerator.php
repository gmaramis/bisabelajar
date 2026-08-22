<?php

namespace App\Contracts\Research;

use App\Exceptions\ReassessmentGenerationException;

/**
 * AI-assisted reassessment candidate generator (M5-04).
 *
 * Implementations may use an LLM or a deterministic stub.
 * They must not decide eligibility, weakness, or Learning State.
 */
interface ReassessmentCandidateGenerator
{
    /**
     * Generate candidate content from a rule-based reassessment specification.
     *
     * The specification must not include learner PII (email, name, phone).
     *
     * @param  array<string, mixed>  $specification
     * @return array{
     *     title: string,
     *     task_prompt: string,
     *     scenario: string,
     *     concept: string,
     *     learning_objective: ?string,
     *     bloom_demand: string,
     *     dave_demand: ?string,
     *     task_format: string,
     *     expected_outcome: string,
     *     rubric: string,
     *     includes_direct_answer: bool,
     *     generator_identity: string,
     *     generator_model: ?string,
     *     metadata: array<string, mixed>
     * }
     *
     * @throws ReassessmentGenerationException
     */
    public function generate(array $specification): array;
}
