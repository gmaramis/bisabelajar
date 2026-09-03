<?php

return [

    'default' => env('AI_DEFAULT_PROVIDER', 'gemini'),
    'socratic' => env('AI_SOCRATIC_PROVIDER', 'gemini'),
    'reassessment' => env('AI_REASSESSMENT_PROVIDER', 'groq'),
    'fast' => env('AI_FAST_PROVIDER', 'cerebras'),
    'fallback' => env('AI_FALLBACK_PROVIDER', 'openrouter'),

    'timeout' => (int) env('AI_TIMEOUT', 30),
    'max_retries' => (int) env('AI_MAX_RETRIES', 3),

    'providers' => [

        'gemini' => [
            'keys' => array_filter([
                env('GEMINI_API_KEY'),
                env('GEMINI_API_KEY_2'),
                env('GEMINI_API_KEY_3'),
            ]),
            'model' => env('GEMINI_MODEL', 'gemini-3.8-flash'),
            'model_lite' => env('GEMINI_MODEL_LITE', 'gemini-3.5-flash-lite'),
            'base_url' => env('GEMINI_API_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        ],

        'groq' => [
            'key' => env('GROQ_API_KEY'),
            'model' => env('GROQ_MODEL', 'groq/compound'),
            'model_mini' => env('GROQ_MODEL_MINI', 'groq/compound-mini'),
            'base_url' => env('GROQ_API_BASE_URL', 'https://api.groq.com/openai/v1'),
        ],

        'cerebras' => [
            'key' => env('CEREBRAS_API_KEY'),
            'model' => env('CEREBRAS_MODEL', 'gpt-oss-120b'),
            'model_fast' => env('CEREBRAS_MODEL_FAST', 'gemma-4-31b'),
            'base_url' => env('CEREBRAS_API_BASE_URL', 'https://api.cerebras.ai/v1'),
        ],

        'openrouter' => [
            'key' => env('OPENROUTER_API_KEY'),
            'model' => env('OPENROUTER_MODEL', 'minimax/minimax-m3:free'),
            'model_fast' => env('OPENROUTER_MODEL_FAST', 'z-ai/glm-5.2:free'),
            'base_url' => env('OPENROUTER_API_BASE_URL', 'https://openrouter.ai/api/v1'),
            'referrer' => env('OPENROUTER_REFERRER', 'https://bisabelajar.com'),
        ],

    ],

];
