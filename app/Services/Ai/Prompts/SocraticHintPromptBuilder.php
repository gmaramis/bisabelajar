<?php

namespace App\Services\Ai\Prompts;

use App\Enums\SocraticResponseType;

final class SocraticHintPromptBuilder
{
    private const SYSTEM_PROMPT = <<<'SYSTEM'
You are NEXUS, a Socratic learning assistant for the BisaBelajar programming education platform.

YOUR ROLE:
- Guide students to discover the solution themselves through targeted questions and conceptual hints.
- Never provide complete code solutions, working code snippets, or direct answers.
- Focus on the underlying concept, logic error, or missing understanding.

STRICT RULES (NEVER VIOLATE):
1. Do NOT write or complete any code for the student.
2. Do NOT give step-by-step instructions that amount to a solution.
3. Do NOT reveal the expected output or test case results.
4. Ask ONE focused guiding question per response, or provide ONE conceptual hint.
5. Keep responses concise (2–4 sentences max).
6. Use plain, encouraging language suitable for a student learner.
7. If you cannot help without violating these rules, say: "Let's think about the concept together — what does [concept] mean in your own words?"

RESPONSE TYPES you may use:
- Clarifying question: Ask what the student thinks a specific line/variable does.
- Concept check: Probe understanding of the relevant concept (e.g., "What does a loop iteration do?").
- Guided question: Point attention to the specific area of the error without naming the fix.
- Reflection question: Ask the student to trace execution mentally.
- Next-step hint: Suggest ONE general direction without implementation details.
SYSTEM;

    public function buildSystemPrompt(): string
    {
        return self::SYSTEM_PROMPT;
    }

    /**
     * @param  array{concept: string, learning_objective: string|null, bloom_demand: string|null, dave_demand: string|null, difficulty: string|int|null}  $activityContext
     * @param  array{error_message: string|null, test_case_label: string|null, attempt_count: int}  $attemptContext
     */
    public function buildUserPrompt(
        array $activityContext,
        array $attemptContext,
        ?SocraticResponseType $preferredType = null,
    ): string {
        $concept = $this->sanitize($activityContext['concept'] ?? 'programming');
        $objective = $this->sanitize($activityContext['learning_objective'] ?? null);
        $bloom = $this->sanitize($activityContext['bloom_demand'] ?? null);
        $difficulty = $this->sanitize((string) ($activityContext['difficulty'] ?? ''));

        $error = $this->sanitize($attemptContext['error_message'] ?? null);
        $testLabel = $this->sanitize($attemptContext['test_case_label'] ?? null);
        $attempts = max(1, (int) ($attemptContext['attempt_count'] ?? 1));

        $lines = [];
        $lines[] = "## Activity Context";
        $lines[] = "Concept: {$concept}";

        if ($objective !== null) {
            $lines[] = "Learning objective: {$objective}";
        }
        if ($bloom !== null) {
            $lines[] = "Cognitive task demand (Bloom): {$bloom}";
        }
        if ($difficulty !== '') {
            $lines[] = "Difficulty: {$difficulty}";
        }

        $lines[] = '';
        $lines[] = "## Student Situation";
        $lines[] = "Attempts so far: {$attempts}";

        if ($error !== null) {
            $lines[] = "Error encountered: {$error}";
        }
        if ($testLabel !== null) {
            $lines[] = "Failing test: {$testLabel}";
        }

        $lines[] = '';

        if ($preferredType !== null) {
            $lines[] = "Preferred response style: {$preferredType->value}";
            $lines[] = '';
        }

        $lines[] = "Please provide a single Socratic hint or guiding question to help the student move forward. Do NOT write any code.";

        return implode("\n", $lines);
    }

    private function sanitize(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = mb_substr(trim($value), 0, 500);
        $value = preg_replace('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', '[redacted]', $value) ?? $value;
        $value = preg_replace('/\b\d[\d\s\-().]{7,}\d\b/', '[redacted]', $value) ?? $value;

        return $value;
    }
}
