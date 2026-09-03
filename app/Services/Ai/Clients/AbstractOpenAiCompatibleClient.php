<?php

namespace App\Services\Ai\Clients;

use App\Contracts\Ai\AiClientInterface;
use App\Exceptions\Ai\AiAuthException;
use App\Exceptions\Ai\AiClientException;
use App\Exceptions\Ai\AiRateLimitException;
use App\Exceptions\Ai\AiResponseException;
use App\Exceptions\Ai\AiTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

abstract class AbstractOpenAiCompatibleClient implements AiClientInterface
{
    public function generate(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        $payload = [
            'model' => $options['model'] ?? $this->getModelName(),
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'max_tokens' => $options['max_tokens'] ?? 1024,
            'temperature' => $options['temperature'] ?? 0.7,
        ];

        try {
            $response = Http::withHeaders($this->buildHeaders())
                ->timeout((int) config('ai.timeout', 30))
                ->post($this->getBaseUrl().'/chat/completions', $payload);
        } catch (ConnectionException $e) {
            throw new AiTimeoutException(
                'Connection to '.$this->getProviderName().' timed out: '.$e->getMessage(),
                $this->getProviderName(),
                'connection_timeout',
                $e,
            );
        }

        return match (true) {
            $response->status() === 401 => throw new AiAuthException(
                'Invalid API key for '.$this->getProviderName().'.',
                $this->getProviderName(),
                'invalid_api_key',
            ),
            $response->status() === 429 => throw new AiRateLimitException(
                $this->getProviderName().' rate limit exceeded.',
                $this->getProviderName(),
                'rate_limit',
            ),
            ! $response->successful() => throw new AiClientException(
                $this->getProviderName().' returned HTTP '.$response->status().': '.$response->body(),
                $this->getProviderName(),
                'http_error_'.$response->status(),
            ),
            default => $this->extractContent($response->json()),
        };
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    protected function extractContent(?array $json): string
    {
        $content = $json['choices'][0]['message']['content'] ?? null;

        if (! is_string($content) || $content === '') {
            throw new AiResponseException(
                'Unexpected response structure from '.$this->getProviderName().': '.json_encode($json),
                $this->getProviderName(),
                'malformed_response',
            );
        }

        return $content;
    }

    abstract protected function getBaseUrl(): string;

    /**
     * @return array<string, string>
     */
    abstract protected function buildHeaders(): array;
}
