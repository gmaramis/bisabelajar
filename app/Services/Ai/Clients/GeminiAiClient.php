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

final class GeminiAiClient implements AiClientInterface
{
    /** @var list<string> */
    private array $keys;

    private string $model;

    private string $baseUrl;

    private static int $keyIndex = 0;

    public function __construct()
    {
        $config = config('ai.providers.gemini');

        $this->keys = array_values(array_filter((array) ($config['keys'] ?? [])));
        $this->model = (string) ($config['model'] ?? 'gemini-3.8-flash');
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta'), '/');
    }

    public function generate(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        $key = $this->nextKey();
        $model = $options['model'] ?? $this->model;

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $userPrompt]]],
            ],
            'generationConfig' => [
                'maxOutputTokens' => $options['max_tokens'] ?? 1024,
                'temperature' => $options['temperature'] ?? 0.7,
            ],
        ];

        $url = "{$this->baseUrl}/models/{$model}:generateContent?key={$key}";

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout((int) config('ai.timeout', 30))
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            throw new AiTimeoutException(
                'Connection to Gemini timed out: '.$e->getMessage(),
                $this->getProviderName(),
                'connection_timeout',
                $e,
            );
        }

        return match (true) {
            $response->status() === 401,
            $response->status() === 403 => throw new AiAuthException(
                'Invalid or revoked Gemini API key.',
                $this->getProviderName(),
                'invalid_api_key',
            ),
            $response->status() === 429 => throw new AiRateLimitException(
                'Gemini rate limit exceeded for current key.',
                $this->getProviderName(),
                'rate_limit',
            ),
            ! $response->successful() => throw new AiClientException(
                'Gemini returned HTTP '.$response->status().': '.$response->body(),
                $this->getProviderName(),
                'http_error_'.$response->status(),
            ),
            default => $this->extractContent($response->json()),
        };
    }

    public function getProviderName(): string
    {
        return 'gemini';
    }

    public function getModelName(): string
    {
        return $this->model;
    }

    private function nextKey(): string
    {
        if ($this->keys === []) {
            throw new AiAuthException(
                'No Gemini API keys configured.',
                $this->getProviderName(),
                'no_api_key',
            );
        }

        $key = $this->keys[self::$keyIndex % count($this->keys)];
        self::$keyIndex++;

        return $key;
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function extractContent(?array $json): string
    {
        $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (! is_string($text) || $text === '') {
            throw new AiResponseException(
                'Unexpected Gemini response structure: '.json_encode($json),
                $this->getProviderName(),
                'malformed_response',
            );
        }

        return $text;
    }
}
