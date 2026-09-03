<?php

namespace Tests\Feature\Ai;

use App\Exceptions\ReassessmentGenerationException;
use App\Services\Ai\AiClientManager;
use App\Services\Ai\Prompts\ReassessmentPromptBuilder;
use App\Services\Research\Reassessment\DeterministicReassessmentCandidateGenerator;
use App\Services\Research\Reassessment\LlmReassessmentCandidateGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LlmReassessmentGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function makeGenerator(): LlmReassessmentCandidateGenerator
    {
        config([
            'ai.reassessment'              => 'groq',
            'ai.providers.groq.key'        => 'test-groq-key',
            'ai.providers.groq.model'      => 'groq/compound',
            'ai.providers.cerebras.key'    => 'test-cerebras-key',
            'ai.providers.openrouter.key'  => 'test-openrouter-key',
        ]);

        return new LlmReassessmentCandidateGenerator(
            new AiClientManager(),
            new ReassessmentPromptBuilder(),
            new DeterministicReassessmentCandidateGenerator(),
        );
    }

    private function validLlmResponse(): array
    {
        return [
            'choices' => [[
                'message' => ['content' => json_encode([
                    'title'                 => 'New For Loop Exercise',
                    'task_prompt'           => 'Write a program that prints each item in a list using a for loop.',
                    'scenario'              => 'A librarian needs to print book titles.',
                    'concept'              => 'for loops',
                    'learning_objective'   => 'Apply iteration to traverse a list.',
                    'bloom_demand'         => 'apply',
                    'dave_demand'          => null,
                    'task_format'          => 'coding_exercise',
                    'expected_outcome'     => 'Each list item printed on a new line.',
                    'rubric'               => 'Correct use of for loop; no hardcoded output.',
                    'includes_direct_answer' => false,
                ])],
            ]],
        ];
    }

    public function test_valid_llm_response_returns_candidate(): void
    {
        Http::fake(['api.groq.com/*' => Http::response($this->validLlmResponse(), 200)]);

        $result = $this->makeGenerator()->generate([
            'concept'      => 'for loops',
            'bloom_demand' => 'apply',
            'constraints'  => ['task_format' => 'coding_exercise'],
        ]);

        $this->assertSame('for loops', $result['concept']);
        $this->assertFalse($result['includes_direct_answer']);
        $this->assertTrue($result['metadata']['ai_assisted']);
        $this->assertFalse($result['metadata']['llm_decision_maker']);
        $this->assertSame('llm_reassessment_candidate_generator', $result['generator_identity']);
    }

    public function test_direct_answer_true_throws_reassessment_exception(): void
    {
        $badResponse = [
            'choices' => [[
                'message' => ['content' => json_encode([
                    'title' => 'T', 'task_prompt' => 'P', 'scenario' => 'S',
                    'concept' => 'loops', 'bloom_demand' => 'apply',
                    'task_format' => 'coding_exercise',
                    'expected_outcome' => 'E', 'rubric' => 'R',
                    'includes_direct_answer' => true, // ← violates guardrail
                ])],
            ]],
        ];

        Http::fake(['api.groq.com/*' => Http::response($badResponse, 200)]);

        $this->expectException(ReassessmentGenerationException::class);
        $this->expectExceptionMessageMatches('/guardrail/');

        $this->makeGenerator()->generate([
            'concept' => 'loops', 'bloom_demand' => 'apply',
        ]);
    }

    public function test_all_providers_fail_falls_back_to_deterministic(): void
    {
        Http::fake([
            'api.groq.com/*'    => Http::response([], 429),
            'api.cerebras.ai/*' => Http::response([], 429),
            'openrouter.ai/*'   => Http::response([], 500),
        ]);

        $result = $this->makeGenerator()->generate([
            'concept'      => 'recursion',
            'bloom_demand' => 'apply',
        ]);

        // Falls back to deterministic — ai_assisted is false
        $this->assertFalse($result['metadata']['ai_assisted']);
        $this->assertSame('deterministic_reassessment_candidate_generator', $result['generator_identity']);
    }

    public function test_candidate_does_not_contain_pii_in_output(): void
    {
        Http::fake(['api.groq.com/*' => Http::response($this->validLlmResponse(), 200)]);

        $result = $this->makeGenerator()->generate([
            'concept'      => 'for loops',
            'bloom_demand' => 'apply',
        ]);

        $serialized = json_encode($result);
        $this->assertStringNotContainsString('@', $serialized, 'Result contains what looks like an email');
        $this->assertStringNotContainsString('user_id', $serialized);
    }
}
