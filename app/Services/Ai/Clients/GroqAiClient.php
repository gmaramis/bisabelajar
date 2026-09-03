<?php

namespace App\Services\Ai\Clients;

final class GroqAiClient extends AbstractOpenAiCompatibleClient
{
    private string $key;

    private string $model;

    private string $baseUrl;

    public function __construct()
    {
        $config = config('ai.providers.groq');

        $this->key     = (string) ($config['key'] ?? '');
        $this->model   = (string) ($config['model'] ?? 'groq/compound');
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.groq.com/openai/v1'), '/');
    }

    public function getProviderName(): string
    {
        return 'groq';
    }

    public function getModelName(): string
    {
        return $this->model;
    }

    protected function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /** @return array<string, string> */
    protected function buildHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->key,
            'Content-Type'  => 'application/json',
        ];
    }
}
