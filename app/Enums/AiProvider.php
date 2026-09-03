<?php

namespace App\Enums;

enum AiProvider: string
{
    case Gemini = 'gemini';
    case Groq = 'groq';
    case Cerebras = 'cerebras';
    case OpenRouter = 'openrouter';

    /**
     * @return list<self>
     */
    public function fallbackChain(): array
    {
        return match ($this) {
            self::Gemini => [self::Gemini, self::OpenRouter],
            self::Groq => [self::Groq, self::Cerebras, self::OpenRouter],
            self::Cerebras => [self::Cerebras, self::OpenRouter],
            self::OpenRouter => [self::OpenRouter],
        };
    }

    public static function fromConfig(string $key): self
    {
        return self::from((string) config('ai.'.$key, self::Gemini->value));
    }
}
