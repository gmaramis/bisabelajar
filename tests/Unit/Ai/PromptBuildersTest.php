<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Prompts\SocraticHintPromptBuilder;
use App\Services\Ai\Prompts\ReassessmentPromptBuilder;
use App\Enums\SocraticResponseType;
use Tests\TestCase;

class PromptBuildersTest extends TestCase
{
    // ── SocraticHintPromptBuilder ─────────────────────────────────────────────

    public function test_system_prompt_contains_no_code_guardrail(): void
    {
        $builder = new SocraticHintPromptBuilder();
        $sys = $builder->buildSystemPrompt();

        $this->assertStringContainsString('Do NOT write or complete any code', $sys);
        $this->assertStringContainsString('NEXUS', $sys);
        $this->assertStringContainsString('direct answer', $sys);
    }

    public function test_user_prompt_contains_concept_and_bloom(): void
    {
        $builder = new SocraticHintPromptBuilder();
        $prompt = $builder->buildUserPrompt(
            ['concept' => 'recursion', 'bloom_demand' => 'apply', 'learning_objective' => null, 'dave_demand' => null, 'difficulty' => null],
            ['error_message' => 'RecursionError', 'test_case_label' => 'Test 1', 'attempt_count' => 2],
        );

        $this->assertStringContainsString('recursion', $prompt);
        $this->assertStringContainsString('apply', $prompt);
        $this->assertStringContainsString('RecursionError', $prompt);
        $this->assertStringContainsString('2', $prompt); // attempt count
    }

    public function test_email_pii_is_redacted_in_user_prompt(): void
    {
        $builder = new SocraticHintPromptBuilder();
        $prompt = $builder->buildUserPrompt(
            ['concept' => 'loops'],
            ['error_message' => 'Error reported by admin@school.edu', 'attempt_count' => 1],
        );

        $this->assertStringNotContainsString('admin@school.edu', $prompt);
        $this->assertStringContainsString('[redacted]', $prompt);
    }

    public function test_user_id_never_in_prompt(): void
    {
        $builder = new SocraticHintPromptBuilder();
        $prompt = $builder->buildUserPrompt(
            ['concept' => 'arrays', 'user_id' => 99, 'email' => 'student@test.com'],
            ['error_message' => null, 'attempt_count' => 1],
        );

        $this->assertStringNotContainsString('user_id', $prompt);
        // email passed via activityContext should not appear (not a standard key)
        $this->assertStringNotContainsString('student@test.com', $prompt);
    }

    public function test_response_type_included_when_provided(): void
    {
        $builder = new SocraticHintPromptBuilder();
        $prompt = $builder->buildUserPrompt(
            ['concept' => 'loops'],
            ['error_message' => null, 'attempt_count' => 1],
            SocraticResponseType::GuidedQuestion,
        );

        $this->assertStringContainsString('guided_question', $prompt);
    }

    public function test_long_input_truncated_to_prevent_injection(): void
    {
        $builder = new SocraticHintPromptBuilder();
        $longError = str_repeat('A', 2000);

        $prompt = $builder->buildUserPrompt(
            ['concept' => 'sorting'],
            ['error_message' => $longError, 'attempt_count' => 1],
        );

        // Error should be truncated, not full 2000 chars
        $this->assertLessThan(3000, strlen($prompt));
    }

    // ── ReassessmentPromptBuilder ─────────────────────────────────────────────

    public function test_reassessment_system_prompt_includes_direct_answer_false(): void
    {
        $builder = new ReassessmentPromptBuilder();
        $sys = $builder->buildSystemPrompt();

        $this->assertStringContainsString('includes_direct_answer', $sys);
        $this->assertStringContainsString('false', $sys);
        $this->assertStringContainsString('JSON', $sys);
    }

    public function test_reassessment_user_prompt_contains_concept(): void
    {
        $builder = new ReassessmentPromptBuilder();
        $prompt = $builder->buildUserPrompt([
            'concept'      => 'for loops',
            'bloom_demand' => 'apply',
            'constraints'  => ['task_format' => 'coding_exercise'],
        ]);

        $this->assertStringContainsString('for loops', $prompt);
        $this->assertStringContainsString('apply', $prompt);
    }

    public function test_parse_valid_json_returns_array(): void
    {
        $builder = new ReassessmentPromptBuilder();
        $json = json_encode([
            'title' => 'T', 'task_prompt' => 'P', 'scenario' => 'S',
            'concept' => 'loops', 'learning_objective' => null,
            'bloom_demand' => 'apply', 'dave_demand' => null,
            'task_format' => 'coding_exercise',
            'expected_outcome' => 'E', 'rubric' => 'R',
            'includes_direct_answer' => false,
        ]);

        $result = $builder->parseAndValidate($json);
        $this->assertFalse($result['includes_direct_answer']);
    }

    public function test_parse_throws_when_includes_direct_answer_is_true(): void
    {
        $builder = new ReassessmentPromptBuilder();
        $json = json_encode([
            'title' => 'T', 'task_prompt' => 'P', 'scenario' => 'S',
            'concept' => 'loops', 'bloom_demand' => 'apply',
            'task_format' => 'coding', 'expected_outcome' => 'E', 'rubric' => 'R',
            'includes_direct_answer' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/guardrail/');
        $builder->parseAndValidate($json);
    }

    public function test_parse_strips_markdown_fences(): void
    {
        $builder = new ReassessmentPromptBuilder();
        $json = "```json\n".json_encode([
            'title' => 'T', 'task_prompt' => 'P', 'scenario' => 'S',
            'concept' => 'loops', 'bloom_demand' => 'apply',
            'task_format' => 'coding', 'expected_outcome' => 'E', 'rubric' => 'R',
            'includes_direct_answer' => false,
        ])."\n```";

        $result = $builder->parseAndValidate($json);
        $this->assertFalse($result['includes_direct_answer']);
    }

    public function test_parse_throws_on_missing_required_field(): void
    {
        $builder = new ReassessmentPromptBuilder();
        $json = json_encode([
            'title' => 'T',
            // missing task_prompt, scenario, etc.
        ]);

        $this->expectException(\RuntimeException::class);
        $builder->parseAndValidate($json);
    }
}
