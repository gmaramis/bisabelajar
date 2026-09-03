<?php

namespace Tests\Performance\Ai;

use App\Services\Ai\Clients\GeminiAiClient;
use App\Services\Ai\Clients\GroqAiClient;
use App\Services\Ai\Clients\CerebrasAiClient;
use App\Services\Ai\Clients\OpenRouterAiClient;
use App\Exceptions\Ai\AiClientException;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Real-API performance tests for all 4 AI providers.
 *
 * These tests call the REAL API and are meant for manual benchmarking only.
 * They are EXCLUDED from CI via the @group annotation.
 *
 * Run manually:
 *   php artisan test --group=ai-performance
 *
 * Results are saved to: storage/logs/ai-performance-{date}.json
 */
class AllProvidersPerformanceTest extends TestCase
{
    private const SYSTEM_PROMPT = 'You are a programming tutor. Be concise.';
    private const USER_PROMPT   = 'What is the difference between a list and a tuple in Python? One sentence only.';

    private array $results = [];

    protected function tearDown(): void
    {
        if (! empty($this->results)) {
            $filename  = 'logs/ai-performance-'.date('Y-m-d-His').'.json';
            $content   = json_encode($this->results, JSON_PRETTY_PRINT);
            file_put_contents(storage_path($filename), $content);
            echo "\n📊 Performance report saved to: storage/{$filename}\n";
        }

        parent::tearDown();
    }

    /**
     * @group ai-performance
     */
    public function test_gemini_performance(): void
    {
        $this->runBenchmark('Gemini', new GeminiAiClient());
        $this->assertTrue(true); // Always passes — output is in the report
    }

    /**
     * @group ai-performance
     */
    public function test_groq_performance(): void
    {
        $this->runBenchmark('Groq', new GroqAiClient());
        $this->assertTrue(true);
    }

    /**
     * @group ai-performance
     */
    public function test_cerebras_performance(): void
    {
        $this->runBenchmark('Cerebras', new CerebrasAiClient());
        $this->assertTrue(true);
    }

    /**
     * @group ai-performance
     */
    public function test_openrouter_performance(): void
    {
        $this->runBenchmark('OpenRouter', new OpenRouterAiClient());
        $this->assertTrue(true);
    }

    private function runBenchmark(string $name, $client): void
    {
        $start = microtime(true);

        try {
            $response     = $client->generate(self::SYSTEM_PROMPT, self::USER_PROMPT);
            $elapsed      = round((microtime(true) - $start) * 1000, 2); // ms
            $tokenEstimate = str_word_count($response) * 1.3; // rough token estimate

            $this->results[$name] = [
                'status'            => 'success',
                'provider'          => $client->getProviderName(),
                'model'             => $client->getModelName(),
                'response_time_ms'  => $elapsed,
                'response_length'   => strlen($response),
                'estimated_tokens'  => round($tokenEstimate),
                'response_preview'  => mb_substr($response, 0, 200),
                'is_socratic_safe'  => ! preg_match('/\b(here is the (code|solution|answer)|def |import |print\()/i', $response),
                'timestamp'         => now()->toISOString(),
            ];

            echo "\n✅ {$name}: {$elapsed}ms — ".mb_substr($response, 0, 80)."…\n";

        } catch (AiClientException $e) {
            $elapsed = round((microtime(true) - $start) * 1000, 2);

            $this->results[$name] = [
                'status'           => 'failed',
                'provider'         => $name,
                'failure_code'     => $e->failureCode,
                'message'          => $e->getMessage(),
                'response_time_ms' => $elapsed,
                'timestamp'        => now()->toISOString(),
            ];

            echo "\n❌ {$name}: FAILED ({$e->failureCode}) — {$e->getMessage()}\n";
        }
    }
}
