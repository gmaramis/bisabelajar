<?php

namespace App\Services\Ai\Clients;

final class OpenRouterAiClient extends AbstractOpenAiCompatibleClient
{
    private string $key;

    private string $model;

    private string $baseUrl;

    private string $referrer;

    public function __construct()
    {
        $config = config('ai.providers.openrouter');

        $this->key      = (string) ($config['key'] ?? '');
        $this->model    = (string) ($config['model'] ?? 'minimax/minimax-m3:free');
        $this->baseUrl  = rtrim((string) ($config['base_url'] ?? 'https://openrouter.ai/api/v1'), '/');
        $this->referrer = (string) ($config['referrer'] ?? 'https://bisabelajar.com');
    }

    public function getProviderName(): string
    {
        return 'openrouter';
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
            'HTTP-Referer'  => $this->referrer,
            'X-Title'       => 'BisaBelajar NEXUS',
        ];
    }
}
