<?php

namespace Tests\Unit\Ai;

use App\Enums\AiProvider;
use App\Exceptions\Ai\AiRateLimitException;
use App\Exceptions\Ai\AllProvidersFailedException;
use App\Services\Ai\AiClientManager;
use App\Services\Ai\Clients\CerebrasAiClient;
use App\Services\Ai\Clients\GeminiAiClient;
use App\Services\Ai\Clients\GroqAiClient;
use App\Services\Ai\Clients\OpenRouterAiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiClientManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();

        config([
            'ai.default'      => 'gemini',
            'ai.socratic'     => 'gemini',
            'ai.reassessment' => 'groq',
            'ai.fast'         => 'cerebras',
            'ai.fallback'     => 'openrouter',
            'ai.providers.gemini.keys'         => ['key1', 'key2', 'key3'],
            'ai.providers.groq.key'            => 'groq-key',
            'ai.providers.cerebras.key'        => 'cerebras-key',
            'ai.providers.openrouter.key'      => 'openrouter-key',
        ]);
    }

    public function test_for_socratic_returns_gemini(): void
    {
        $client = (new AiClientManager())->forSocratic();
        $this->assertInstanceOf(GeminiAiClient::class, $client);
    }

    public function test_for_reassessment_returns_groq(): void
    {
        $client = (new AiClientManager())->forReassessment();
        $this->assertInstanceOf(GroqAiClient::class, $client);
    }

    public function test_for_fast_returns_cerebras(): void
    {
        $client = (new AiClientManager())->forFast();
        $this->assertInstanceOf(CerebrasAiClient::class, $client);
    }

    public function test_fallback_used_when_primary_rate_limited(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 429), // Gemini fails
            'openrouter.ai/*'                     => Http::response([
                'choices' => [['message' => ['content' => 'fallback answer']]],
            ], 200),
        ]);

        $result = (new AiClientManager())->generateWithFailover(
            AiProvider::Gemini,
            'sys',
            'usr',
        );

        $this->assertSame('fallback answer', $result);
    }

    public function test_groq_failover_to_cerebras_to_openrouter(): void
    {
        Http::fake([
            'api.groq.com/*'    => Http::response([], 429),
            'api.cerebras.ai/*' => Http::response([], 429),
            'openrouter.ai/*'   => Http::response([
                'choices' => [['message' => ['content' => 'final fallback']]],
            ], 200),
        ]);

        $result = (new AiClientManager())->generateWithFailover(
            AiProvider::Groq,
            'sys',
            'usr',
        );

        $this->assertSame('final fallback', $result);
    }

    public function test_all_providers_failed_throws_exception(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 429),
            'openrouter.ai/*'                     => Http::response([], 500),
        ]);

        $this->expectException(AllProvidersFailedException::class);

        (new AiClientManager())->generateWithFailover(
            AiProvider::Gemini,
            'sys',
            'usr',
        );
    }

    public function test_unconfigured_provider_skipped_in_chain(): void
    {
        // Remove cerebras key → cerebras skipped, falls to openrouter
        config(['ai.providers.cerebras.key' => '']);

        Http::fake([
            'api.groq.com/*'  => Http::response([], 429),
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => 'openrouter result']]],
            ], 200),
        ]);

        $result = (new AiClientManager())->generateWithFailover(
            AiProvider::Groq,
            'sys',
            'usr',
        );

        $this->assertSame('openrouter result', $result);
    }
}
