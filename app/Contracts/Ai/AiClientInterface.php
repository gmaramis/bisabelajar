<?php

namespace App\Contracts\Ai;

interface AiClientInterface
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function generate(string $systemPrompt, string $userPrompt, array $options = []): string;

    public function getProviderName(): string;

    public function getModelName(): string;
}
