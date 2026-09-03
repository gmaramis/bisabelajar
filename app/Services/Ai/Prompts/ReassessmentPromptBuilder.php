<?php

namespace App\Services\Ai\Prompts;

final class ReassessmentPromptBuilder
{
    private const SYSTEM_PROMPT = <<<'SYSTEM'
You are an expert educational content designer specialising in programming assessment for vocational learners.

YOUR TASK:
Create a NEW reassessment exercise based on the provided specification. The exercise must:
1. Address the same concept and learning objective as specified.
2. Use a completely different scenario, context, and wording from any prior activity.
3. Match the specified Bloom cognitive demand level.
4. Be solvable at the specified difficulty without the learner needing to look up new material.
5. NEVER include the answer, working solution, or any code that solves the problem.

OUTPUT FORMAT:
Respond with ONLY a valid JSON object. No markdown fences, no explanation outside the JSON.
The JSON must have exactly these fields:
{
  "title": "string — short exercise title",
  "task_prompt": "string — the task description shown to the student (no solution code)",
  "scenario": "string — the real-world context framing the exercise",
  "concept": "string — the programming concept being tested (copy from specification)",
  "learning_objective": "string or null",
  "bloom_demand": "string — Bloom level (copy from specification)",
  "dave_demand": "string or null — Dave level if applicable",
  "task_format": "string — e.g. coding_exercise, short_answer",
  "expected_outcome": "string — what a correct solution must demonstrate (no code)",
  "rubric": "string — evaluation criteria for a human tutor",
  "includes_direct_answer": false
}

CRITICAL: includes_direct_answer MUST be the boolean false. Never true.
SYSTEM;

    public function buildSystemPrompt(): string
    {
        return self::SYSTEM_PROMPT;
    }

    /**
     * @param  array<string, mixed>  $specification
     */
    public function buildUserPrompt(array $specification): string
    {
        $concept = (string) ($specification['concept'] ?? '');
        $objective = $specification['learning_objective'] ?? null;
        $bloom = (string) ($specification['bloom_demand'] ?? '');
        $dave = $specification['dave_demand'] ?? null;
        $format = (string) ($specification['constraints']['task_format'] ?? 'coding_exercise');
        $difficulty = $specification['constraints']['difficulty'] ?? null;
        $language = $specification['constraints']['programming_language'] ?? null;

        $lines = [];
        $lines[] = '## Reassessment Specification';
        $lines[] = "Concept: {$concept}";

        if (is_string($objective) && $objective !== '') {
            $lines[] = "Learning objective: {$objective}";
        }

        $lines[] = "Bloom cognitive demand: {$bloom}";

        if (is_string($dave) && $dave !== '') {
            $lines[] = "Dave psychomotor demand: {$dave}";
        }

        $lines[] = "Task format: {$format}";

        if ($difficulty !== null) {
            $lines[] = "Difficulty: {$difficulty}";
        }

        if (is_string($language) && $language !== '') {
            $lines[] = "Programming language: {$language}";
        }

        $lines[] = '';
        $lines[] = 'Generate a new reassessment exercise following the system instructions.';
        $lines[] = 'The scenario must differ from any prior exercise on this concept.';
        $lines[] = 'Return ONLY the JSON object. Do not include markdown or explanation.';

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    public function parseAndValidate(string $rawOutput): array
    {
        $cleaned = trim(preg_replace('/^```(?:json)?\s*/m', '', preg_replace('/\s*```$/m', '', $rawOutput)) ?? $rawOutput);

        $data = json_decode($cleaned, associative: true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new \RuntimeException('LLM output is not a JSON object.');
        }

        $required = ['title', 'task_prompt', 'scenario', 'concept', 'bloom_demand', 'task_format', 'expected_outcome', 'rubric', 'includes_direct_answer'];

        foreach ($required as $field) {
            if (! array_key_exists($field, $data)) {
                throw new \RuntimeException("LLM output missing required field: {$field}");
            }
        }

        if ($data['includes_direct_answer'] !== false) {
            throw new \RuntimeException('LLM output violates guardrail: includes_direct_answer must be false.');
        }

        return $data;
    }
}
