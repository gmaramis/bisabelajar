<?php

namespace App\Services\Research\Reassessment;

use App\Contracts\Research\ReassessmentCandidateGenerator;
use App\Enums\AiProvider;
use App\Exceptions\Ai\AiClientException;
use App\Exceptions\ReassessmentGenerationException;
use App\Services\Ai\AiClientManager;
use App\Services\Ai\Prompts\ReassessmentPromptBuilder;

final class LlmReassessmentCandidateGenerator implements ReassessmentCandidateGenerator
{
    public function __construct(
        private readonly AiClientManager $manager,
        private readonly ReassessmentPromptBuilder $promptBuilder,
        private readonly DeterministicReassessmentCandidateGenerator $deterministicFallback,
    ) {}

    public function generate(array $specification): array
    {
        $systemPrompt = $this->promptBuilder->buildSystemPrompt();
        $userPrompt = $this->promptBuilder->buildUserPrompt($specification);

        try {
            $raw = $this->manager->generateWithFailover(
                AiProvider::fromConfig('reassessment'),
                $systemPrompt,
                $userPrompt,
                ['max_tokens' => 1024, 'temperature' => 0.8],
            );

            $data = $this->promptBuilder->parseAndValidate($raw);

            return [
                'title' => (string) ($data['title'] ?? ''),
                'task_prompt' => (string) ($data['task_prompt'] ?? ''),
                'scenario' => (string) ($data['scenario'] ?? ''),
                'concept' => (string) ($data['concept'] ?? $specification['concept'] ?? ''),
                'learning_objective' => isset($data['learning_objective']) && is_string($data['learning_objective'])
                    ? $data['learning_objective']
                    : null,
                'bloom_demand' => (string) ($data['bloom_demand'] ?? $specification['bloom_demand'] ?? ''),
                'dave_demand' => isset($data['dave_demand']) && is_string($data['dave_demand'])
                    ? $data['dave_demand']
                    : null,
                'task_format' => (string) ($data['task_format'] ?? 'coding_exercise'),
                'expected_outcome' => (string) ($data['expected_outcome'] ?? ''),
                'rubric' => (string) ($data['rubric'] ?? ''),
                'includes_direct_answer' => false,
                'generator_identity' => 'llm_reassessment_candidate_generator',
                'generator_model' => $this->manager->forReassessment()->getModelName(),
                'metadata' => [
                    'ai_assisted' => true,
                    'llm_decision_maker' => false,
                    'provider' => $this->manager->forReassessment()->getProviderName(),
                    'varies_scenario' => true,
                ],
            ];
        } catch (AiClientException $e) {
            return $this->deterministicFallback->generate($specification);
        } catch (\RuntimeException $e) {
            throw new ReassessmentGenerationException(
                'AI output failed guardrail validation: '.$e->getMessage(),
                'guardrail_violation',
            );
        }
    }
}
