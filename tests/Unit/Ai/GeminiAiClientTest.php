<?php

namespace Tests\Unit\Ai;

use App\Exceptions\Ai\AiAuthException;
use App\Exceptions\Ai\AiRateLimitException;
use App\Exceptions\Ai\AiResponseException;
use App\Exceptions\Ai\AiTimeoutException;
use App\Services\Ai\Clients\GeminiAiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiAiClientTest extends TestCase
{
    private GeminiAiClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        // Reset static round-robin index to ensure predictable key ordering per test
        $reflection = new \ReflectionProperty(\App\Services\Ai\Clients\GeminiAiClient::class, 'keyIndex');
        $reflection->setAccessible(true);
        $reflection->setValue(null, 0);

        config(['ai.providers.gemini' => [
            'keys'      => ['test-key-1', 'test-key-2', 'test-key-3'],
            'model'     => 'gemini-3.8-flash',
            'model_lite'=> 'gemini-3.5-flash-lite',
            'base_url'  => 'https://generativelanguage.googleapis.com/v1beta',
        ]]);
        $this->client = new GeminiAiClient();
    }

    public function test_provider_name_and_model(): void
    {
        $this->assertSame('gemini', $this->client->getProviderName());
        $this->assertSame('gemini-3.8-flash', $this->client->getModelName());
    }

    public function test_successful_response_returns_text(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Think about what a loop does on each iteration.']]],
                ]],
            ], 200),
        ]);

        $result = $this->client->generate('System prompt', 'User prompt');

        $this->assertSame('Think about what a loop does on each iteration.', $result);
    }

    public function test_401_throws_auth_exception(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 401)]);

        $this->expectException(AiAuthException::class);
        $this->client->generate('sys', 'usr');
    }

    public function test_429_throws_rate_limit_exception(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 429)]);

        $this->expectException(AiRateLimitException::class);
        $this->client->generate('sys', 'usr');
    }

    public function test_malformed_response_throws_response_exception(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => []], 200)]);

        $this->expectException(AiResponseException::class);
        $this->client->generate('sys', 'usr');
    }

    public function test_key_rotation_round_robin(): void
    {
        $capturedUrls = [];

        Http::fake(function ($request) use (&$capturedUrls) {
            $capturedUrls[] = (string) $request->url();
            return Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'ok']]],
                ]],
            ], 200);
        });

        $this->client->generate('s', 'u');
        $this->client->generate('s', 'u');
        $this->client->generate('s', 'u');

        // Each call should use a different key
        $keys = array_map(fn ($url) => parse_url($url, PHP_URL_QUERY), $capturedUrls);
        $this->assertCount(3, array_unique($keys), 'Expected 3 different keys to be used');
    }

    public function test_no_keys_throws_auth_exception(): void
    {
        config(['ai.providers.gemini.keys' => []]);
        $client = new GeminiAiClient();

        $this->expectException(AiAuthException::class);
        $client->generate('s', 'u');
    }

    public function test_request_sent_to_correct_endpoint(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => 'ok']]],
            ]],
        ], 200)]);

        $this->client->generate('sys', 'usr');

        Http::assertSent(fn ($req) =>
            str_contains($req->url(), 'generativelanguage.googleapis.com') &&
            str_contains($req->url(), 'generateContent')
        );
    }
}
