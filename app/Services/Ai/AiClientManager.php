<?php

namespace App\Services\Ai;

use App\Contracts\Ai\AiClientInterface;
use App\Enums\AiProvider;
use App\Exceptions\Ai\AiClientException;
use App\Exceptions\Ai\AllProvidersFailedException;
use App\Services\Ai\Clients\CerebrasAiClient;
use App\Services\Ai\Clients\GeminiAiClient;
use App\Services\Ai\Clients\GroqAiClient;
use App\Services\Ai\Clients\OpenRouterAiClient;
use Illuminate\Support\Facades\Log;

final class AiClientManager
{
    public function forSocratic(): AiClientInterface
    {
        return $this->resolve(AiProvider::fromConfig('socratic'));
    }

    public function forReassessment(): AiClientInterface
    {
        return $this->resolve(AiProvider::fromConfig('reassessment'));
    }

    public function forFast(): AiClientInterface
    {
        return $this->resolve(AiProvider::fromConfig('fast'));
    }

    public function forDefault(): AiClientInterface
    {
        return $this->resolve(AiProvider::fromConfig('default'));
    }

    public function resolve(AiProvider $provider): AiClientInterface
    {
        $chain = $provider->fallbackChain();
        $errors = [];

        foreach ($chain as $candidate) {
            $client = $this->make($candidate);

            if ($this->isConfigured($candidate)) {
                return $client;
            }

            $errors[] = $candidate->value.': not configured (missing key)';
        }

        throw new AllProvidersFailedException(
            'All AI providers in fallback chain are unconfigured. Errors: '.implode('; ', $errors),
            $provider->value,
            'all_providers_unconfigured',
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function generateWithFailover(
        AiProvider $provider,
        string $systemPrompt,
        string $userPrompt,
        array $options = [],
    ): string {
        $chain = $provider->fallbackChain();
        $errors = [];

        foreach ($chain as $candidate) {
            if (! $this->isConfigured($candidate)) {
                $errors[] = $candidate->value.': not configured';
                continue;
            }

            $client = $this->make($candidate);

            try {
                return $client->generate($systemPrompt, $userPrompt, $options);
            } catch (AiClientException $e) {
                Log::warning('AI provider failed, trying next in chain', [
                    'provider' => $candidate->value,
                    'failure_code' => $e->failureCode,
                    'message' => $e->getMessage(),
                ]);
                $errors[] = $candidate->value.': '.$e->failureCode.' — '.$e->getMessage();
            }
        }

        throw new AllProvidersFailedException(
            'All AI providers failed. Errors: '.implode(' | ', $errors),
            $provider->value,
            'all_providers_failed',
        );
    }

    private function make(AiProvider $provider): AiClientInterface
    {
        return match ($provider) {
            AiProvider::Gemini => new GeminiAiClient(),
            AiProvider::Groq => new GroqAiClient(),
            AiProvider::Cerebras => new CerebrasAiClient(),
            AiProvider::OpenRouter => new OpenRouterAiClient(),
        };
    }

    private function isConfigured(AiProvider $provider): bool
    {
        $config = config('ai.providers.'.$provider->value);

        return match ($provider) {
            AiProvider::Gemini => ! empty(array_filter((array) ($config['keys'] ?? []))),
            AiProvider::Groq,
            AiProvider::Cerebras,
            AiProvider::OpenRouter => ! empty($config['key'] ?? ''),
        };
    }
}
