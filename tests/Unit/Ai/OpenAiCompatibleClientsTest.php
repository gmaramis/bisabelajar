<?php

namespace Tests\Unit\Ai;

use App\Exceptions\Ai\AiAuthException;
use App\Exceptions\Ai\AiRateLimitException;
use App\Exceptions\Ai\AiResponseException;
use App\Services\Ai\Clients\GroqAiClient;
use App\Services\Ai\Clients\CerebrasAiClient;
use App\Services\Ai\Clients\OpenRouterAiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiCompatibleClientsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();

        config([
            'ai.providers.groq' => [
                'key'      => 'test-groq-key',
                'model'    => 'groq/compound',
                'model_mini' => 'groq/compound-mini',
                'base_url' => 'https://api.groq.com/openai/v1',
            ],
            'ai.providers.cerebras' => [
                'key'        => 'test-cerebras-key',
                'model'      => 'gpt-oss-120b',
                'model_fast' => 'gemma-4-31b',
                'base_url'   => 'https://api.cerebras.ai/v1',
            ],
            'ai.providers.openrouter' => [
                'key'        => 'test-openrouter-key',
                'model'      => 'minimax/minimax-m3:free',
                'model_fast' => 'z-ai/glm-5.2:free',
                'base_url'   => 'https://openrouter.ai/api/v1',
                'referrer'   => 'https://bisabelajar.com',
            ],
        ]);
    }

    private function successResponse(): array
    {
        return ['choices' => [['message' => ['content' => 'Hint text from provider']]]];
    }

    // ── Groq ──────────────────────────────────────────────────────────────────

    public function test_groq_provider_and_model(): void
    {
        $client = new GroqAiClient();
        $this->assertSame('groq', $client->getProviderName());
        $this->assertSame('groq/compound', $client->getModelName());
    }

    public function test_groq_successful_response(): void
    {
        Http::fake(['api.groq.com/*' => Http::response($this->successResponse(), 200)]);
        $result = (new GroqAiClient())->generate('sys', 'usr');
        $this->assertSame('Hint text from provider', $result);
    }

    public function test_groq_429_throws_rate_limit(): void
    {
        Http::fake(['api.groq.com/*' => Http::response([], 429)]);
        $this->expectException(AiRateLimitException::class);
        (new GroqAiClient())->generate('sys', 'usr');
    }

    public function test_groq_401_throws_auth(): void
    {
        Http::fake(['api.groq.com/*' => Http::response([], 401)]);
        $this->expectException(AiAuthException::class);
        (new GroqAiClient())->generate('sys', 'usr');
    }

    public function test_groq_malformed_response_throws_response_exception(): void
    {
        Http::fake(['api.groq.com/*' => Http::response(['choices' => []], 200)]);
        $this->expectException(AiResponseException::class);
        (new GroqAiClient())->generate('sys', 'usr');
    }

    public function test_groq_sends_bearer_auth_header(): void
    {
        Http::fake(['api.groq.com/*' => Http::response($this->successResponse(), 200)]);
        (new GroqAiClient())->generate('sys', 'usr');
        Http::assertSent(fn ($req) => $req->header('Authorization')[0] === 'Bearer test-groq-key');
    }

    // ── Cerebras ──────────────────────────────────────────────────────────────

    public function test_cerebras_provider_and_model(): void
    {
        $client = new CerebrasAiClient();
        $this->assertSame('cerebras', $client->getProviderName());
        $this->assertSame('gpt-oss-120b', $client->getModelName());
    }

    public function test_cerebras_successful_response(): void
    {
        Http::fake(['api.cerebras.ai/*' => Http::response($this->successResponse(), 200)]);
        $result = (new CerebrasAiClient())->generate('sys', 'usr');
        $this->assertSame('Hint text from provider', $result);
    }

    public function test_cerebras_429_throws_rate_limit(): void
    {
        Http::fake(['api.cerebras.ai/*' => Http::response([], 429)]);
        $this->expectException(AiRateLimitException::class);
        (new CerebrasAiClient())->generate('sys', 'usr');
    }

    // ── OpenRouter ────────────────────────────────────────────────────────────

    public function test_openrouter_provider_and_model(): void
    {
        $client = new OpenRouterAiClient();
        $this->assertSame('openrouter', $client->getProviderName());
        $this->assertSame('minimax/minimax-m3:free', $client->getModelName());
    }

    public function test_openrouter_successful_response(): void
    {
        Http::fake(['openrouter.ai/*' => Http::response($this->successResponse(), 200)]);
        $result = (new OpenRouterAiClient())->generate('sys', 'usr');
        $this->assertSame('Hint text from provider', $result);
    }

    public function test_openrouter_sends_referer_header(): void
    {
        Http::fake(['openrouter.ai/*' => Http::response($this->successResponse(), 200)]);
        (new OpenRouterAiClient())->generate('sys', 'usr');
        Http::assertSent(fn ($req) => $req->header('HTTP-Referer')[0] === 'https://bisabelajar.com');
    }

    public function test_openrouter_sends_x_title_header(): void
    {
        Http::fake(['openrouter.ai/*' => Http::response($this->successResponse(), 200)]);
        (new OpenRouterAiClient())->generate('sys', 'usr');
        Http::assertSent(fn ($req) => $req->header('X-Title')[0] === 'BisaBelajar NEXUS');
    }
}
