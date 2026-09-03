<?php

namespace App\Services\Ai;

use App\Enums\AiProvider;
use App\Enums\SocraticResponseType;
use App\Models\Activity;
use App\Services\Ai\Prompts\SocraticHintPromptBuilder;

final class NexusSocraticTutorService
{
    public function __construct(
        private readonly AiClientManager $manager,
        private readonly SocraticHintPromptBuilder $promptBuilder,
    ) {}

    /**
     * @param  array{error_message: string|null, test_case_label: string|null, attempt_count: int}  $attemptContext
     * @return array{hint: string, provider: string, model: string, response_type: string, advisory_only: true, includes_direct_answer: false}
     */
    public function hint(Activity $activity, array $attemptContext): array
    {
        $activityContext = [
            'concept' => $activity->getConcept() ?? 'programming',
            'learning_objective' => $activity->getLearningObjective(),
            'bloom_demand' => $activity->getBloomDemand()?->value,
            'dave_demand' => $activity->getDaveDemand()?->value,
            'difficulty' => $activity->getDifficulty(),
        ];

        $responseType = $this->selectResponseType((int) ($attemptContext['attempt_count'] ?? 1));

        $systemPrompt = $this->promptBuilder->buildSystemPrompt();
        $userPrompt = $this->promptBuilder->buildUserPrompt($activityContext, $attemptContext, $responseType);

        $hint = $this->manager->generateWithFailover(
            AiProvider::fromConfig('socratic'),
            $systemPrompt,
            $userPrompt,
            ['max_tokens' => 256, 'temperature' => 0.6],
        );

        $client = $this->manager->forSocratic();

        return [
            'hint' => trim($hint),
            'provider' => $client->getProviderName(),
            'model' => $client->getModelName(),
            'response_type' => $responseType->value,
            'advisory_only' => true,
            'includes_direct_answer' => false,
        ];
    }

    private function selectResponseType(int $attempts): SocraticResponseType
    {
        return match (true) {
            $attempts <= 1 => SocraticResponseType::ClarifyingQuestion,
            $attempts === 2 => SocraticResponseType::ConceptCheck,
            $attempts === 3 => SocraticResponseType::GuidedQuestion,
            $attempts === 4 => SocraticResponseType::ReflectionQuestion,
            default => SocraticResponseType::NextStepHint,
        };
    }
}
