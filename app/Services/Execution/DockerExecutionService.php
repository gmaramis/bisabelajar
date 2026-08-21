<?php

namespace App\Services\Execution;

use App\Models\CodeExecution;
use App\Models\LanguageExecutionProfile;
use App\Models\ProgrammingActivity;
use App\Models\TestCase;
use App\Models\TestResult;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class DockerExecutionService
{
    private const DEFAULT_TIMEOUT = 30;
    private const DEFAULT_MEMORY_MB = 256;
    private const DEFAULT_CPU_LIMIT = 1;
    private const MAX_SOURCE_SIZE_KB = 100;
    private const MAX_OUTPUT_SIZE_KB = 500;

    /**
     * Execute code in a Docker sandbox.
     */
    public function execute(
        CodeExecution $execution,
        string $sourceCode,
        ?LanguageExecutionProfile $profile = null,
        ?array $additionalFiles = null,
        ?array $testCases = null
    ): array {
        $profile = $profile ?? $execution->languageExecutionProfile;
        
        if (! $profile) {
            return $this->failureResult($execution, 'No language execution profile specified', 'system_error');
        }

        // Validate source code size
        if (strlen($sourceCode) > ($profile->source_code_size_limit_kb ?? self::MAX_SOURCE_SIZE_KB) * 1024) {
            return $this->failureResult($execution, 'Source code exceeds size limit', 'resource_limit');
        }

        $startTime = microtime(true);
        $containerName = 'bisabelajar-exec-' . Str::random(12);
        $workDir = '/workspace';

        try {
            // Create source file
            $sourceFile = $this->createSourceFile($profile, $sourceCode);
            
            // Build Docker command
            $dockerCommand = $this->buildDockerCommand($profile, $containerName, $workDir, $sourceFile, $additionalFiles);
            
            // Execute in Docker
            $result = $this->runContainer($dockerCommand, $profile);
            
            $executionDurationMs = (int)((microtime(true) - $startTime) * 1000);

            // Parse result
            $parsedResult = $this->parseExecutionResult($result, $profile, $executionDurationMs);
            
            // If test cases provided, run evaluation
            if ($testCases && $parsedResult['status'] === 'success') {
                $testSummary = $this->runTests($execution, $testCases, $profile, $containerName, $workDir, $sourceFile, $additionalFiles);
                $parsedResult['test_summary'] = $testSummary;
            }

            return $parsedResult;

        } catch (Exception $e) {
            Log::error('Docker execution failed', [
                'execution_id' => $execution->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return $this->failureResult($execution, 'Execution system error: ' . $e->getMessage(), 'system_error');
        } finally {
            // Cleanup container
            $this->cleanupContainer($containerName);
        }
    }

    /**
     * Run automated tests against the code.
     */
    public function runTests(
        CodeExecution $execution,
        array $testCases,
        LanguageExecutionProfile $profile,
        string $containerName,
        string $workDir,
        string $sourceFile,
        ?array $additionalFiles = null
    ): array {
        $total = count($testCases);
        $passed = 0;
        $failed = 0;
        $visiblePassed = 0;
        $visibleTotal = 0;
        $hiddenPassed = 0;
        $hiddenTotal = 0;
        $testDetails = [];

        foreach ($testCases as $index => $testCase) {
            $isVisible = $testCase['visible'] ?? true;
            
            if ($isVisible) {
                $visibleTotal++;
            } else {
                $hiddenTotal++;
            }

            $testResult = $this->runSingleTest(
                $execution,
                $testCase,
                $profile,
                $containerName,
                $workDir,
                $sourceFile,
                $additionalFiles
            );

            if ($testResult['passed']) {
                $passed++;
                if ($isVisible) $visiblePassed++;
                else $hiddenPassed++;
            } else {
                $failed++;
            }

            // Create TestResult record
            TestResult::create([
                'code_execution_id' => $execution->id,
                'test_case_id' => $testCase['id'],
                'passed' => $testResult['passed'],
                'actual_output' => $testResult['actual_output'] ?? null,
                'actual_error' => $testResult['actual_error'] ?? null,
                'execution_duration_ms' => $testResult['execution_duration_ms'] ?? null,
                'status' => $testResult['status'] ?? 'system_error',
                'metadata' => $testResult['metadata'] ?? null,
            ]);

            $testDetails[] = [
                'test_case_id' => $testCase['id'],
                'passed' => $testResult['passed'],
                'actual_output' => $testResult['actual_output'] ?? null,
                'actual_error' => $testResult['actual_error'] ?? null,
                'execution_duration_ms' => $testResult['execution_duration_ms'] ?? null,
                'status' => $testResult['status'] ?? 'system_error',
                'memory_used_kb' => null,
            ];
        }

        return [
            'total' => $total,
            'passed' => $passed,
            'failed' => $failed,
            'visible_passed' => $visiblePassed,
            'visible_total' => $visibleTotal,
            'hidden_passed' => $hiddenPassed,
            'hidden_total' => $hiddenTotal,
            'details' => $testDetails,
        ];
    }

    /**
     * Run a single test case.
     */
    private function runSingleTest(
        CodeExecution $execution,
        array $testCase,
        LanguageExecutionProfile $profile,
        string $containerName,
        string $workDir,
        string $sourceFile,
        ?array $additionalFiles
    ): array {
        $startTime = microtime(true);
        $input = $testCase['input'] ?? '';
        $expectedOutput = $testCase['expected_output'] ?? '';
        $comparisonStrategy = $testCase['comparison_strategy'] ?? 'exact';
        $timeout = $testCase['timeout_seconds'] ?? $profile->timeout_seconds;

        try {
            // Create input file if needed
            if ($input !== '') {
                $inputFile = tempnam(sys_get_temp_dir(), 'test_input_');
                file_put_contents($inputFile, $input);
            }

            $dockerCommand = $this->buildDockerCommand(
                $profile,
                $containerName . '-test-' . $index,
                $workDir,
                $sourceFile,
                $additionalFiles,
                $input ?? null
            );

            // Adjust timeout for test
            $dockerCommand = str_replace(
                "--memory={$profile->memory_limit_mb}m",
                "--memory={$profile->memory_limit_mb}m --cpus={$profile->cpu_limit}",
                $dockerCommand
            );

            $result = $this->runContainer($dockerCommand, $profile, $timeout);
            $executionDurationMs = (int)((microtime(true) - $startTime) * 1000);

            $stdout = trim($result['stdout'] ?? '');
            $stderr = trim($result['stderr'] ?? '');
            $status = $result['status'] ?? 'system_error';

            // Compare output
            $passed = false;
            if ($status === 'success') {
                $passed = $this->compareOutput($stdout, $expectedOutput, $comparisonStrategy);
            }

            return [
                'passed' => $passed,
                'actual_output' => $stdout,
                'actual_error' => $stderr,
                'execution_duration_ms' => $executionDurationMs,
                'status' => $status,
                'metadata' => [
                    'comparison_strategy' => $comparisonStrategy,
                    'expected_output' => $expectedOutput,
                ],
            ];

        } catch (Exception $e) {
            return [
                'passed' => false,
                'actual_output' => null,
                'actual_error' => $e->getMessage(),
                'execution_duration_ms' => (int)((microtime(true) - $startTime) * 1000),
                'status' => 'system_error',
                'metadata' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Compare actual output with expected using the specified strategy.
     */
    private function compareOutput(string $actual, string $expected, string $strategy): bool
    {
        return match ($strategy) {
            'exact' => trim($actual) === trim($expected),
            'contains' => str_contains($actual, $expected),
            'regex' => (bool)preg_match('/' . $expected . '/', $actual),
            'trimmed' => trim($actual) === trim($expected),
            default => trim($actual) === trim($expected),
        };
    }

    /**
     * Build Docker run command with security restrictions.
     */
    private function buildDockerCommand(
        LanguageExecutionProfile $profile,
        string $containerName,
        string $workDir,
        string $sourceFile,
        ?array $additionalFiles = null,
        ?string $stdinInput = null
    ): string {
        $memoryLimit = $profile->memory_limit_mb ?? self::DEFAULT_MEMORY_MB;
        $cpuLimit = $profile->cpu_limit ?? self::DEFAULT_CPU_LIMIT;
        $timeout = $profile->timeout_seconds ?? self::DEFAULT_TIMEOUT;

        $volumes = [
            $sourceFile . ':' . $workDir . '/' . basename($sourceFile) . ':ro',
        ];

        if ($additionalFiles) {
            foreach ($additionalFiles as $filePath => $containerPath) {
                $volumes[] = $filePath . ':' . $workDir . '/' . $containerPath . ':ro';
            }
        }

        $volumeArgs = implode(' ', array_map(fn($v) => "-v {$v}", $volumes));

        $envArgs = '';
        if ($profile->environment_variables) {
            foreach ($profile->environment_variables as $key => $value) {
                $envArgs .= " -e {$key}=" . escapeshellarg($value);
            }
        }

        $networkArg = $profile->network_enabled ? '' : '--network=none';
        $readOnlyArg = '--read-only';
        $tmpfsArg = '--tmpfs /workspace:rw,noexec,nosuid,size=100m --tmpfs /tmp:rw,noexec,nosuid,size=50m';
        $securityArgs = '--security-opt=no-new-privileges --cap-drop=ALL';
        $pidsLimit = '--pids-limit=50';
        $ulimitArgs = '--ulimit core=0 --ulimit fsize=' . (self::MAX_OUTPUT_SIZE_KB * 1024);

        $command = $profile->run_command;
        if ($stdinInput !== null) {
            $command = "echo " . escapeshellarg($stdinInput) . " | " . $command;
        }

        return "docker run --rm " .
            "--name {$containerName} " .
            "--memory={$memoryLimit}m " .
            "--cpus={$cpuLimit} " .
            "--ulimit cpu={$timeout} " .
            "{$networkArg} " .
            "{$readOnlyArg} " .
            "{$tmpfsArg} " .
            "{$securityArgs} " .
            "{$pidsLimit} " .
            "{$ulimitArgs} " .
            "{$envArgs} " .
            "{$volumeArgs} " .
            "-w {$workDir} " .
            "{$profile->docker_image} " .
            "sh -c " . escapeshellarg($command);
    }

    /**
     * Create temporary source file.
     */
    private function createSourceFile(LanguageExecutionProfile $profile, string $sourceCode): string
    {
        $tempDir = sys_get_temp_dir() . '/bisabelajar_exec_' . Str::random(8);
        @mkdir($tempDir, 0700, true);
        
        $sourceFile = $tempDir . '/' . $profile->source_filename;
        file_put_contents($sourceFile, $sourceCode);
        
        return $sourceFile;
    }

    /**
     * Run Docker container and capture output.
     */
    private function runContainer(string $command, LanguageExecutionProfile $profile, ?int $overrideTimeout = null): array
    {
        $timeout = $overrideTimeout ?? ($profile->timeout_seconds ?? self::DEFAULT_TIMEOUT) + 5; // Extra buffer

        try {
            $result = Process::timeout($timeout)->run($command);
            
            $stdout = $result->output();
            $stderr = $result->errorOutput();
            $exitCode = $result->exitCode();

            // Determine status - check if process timed out by examining stderr or exit code
            // Symfony Process throws ProcessTimedOutException on timeout, so if we get here without exception,
            // it didn't timeout. But we check for common timeout indicators.
            $timedOut = ($exitCode === null || $exitCode === 124 || $exitCode === 137) && 
                       (stripos($stderr, 'timeout') !== false || stripos($stdout, 'timeout') !== false);
            
            if ($timedOut) {
                return [
                    'status' => 'timeout',
                    'stdout' => $stdout,
                    'stderr' => $stderr,
                    'exit_code' => null,
                    'timeout' => true,
                ];
            }

            if ($exitCode === 0) {
                return [
                    'status' => 'success',
                    'stdout' => $stdout,
                    'stderr' => $stderr,
                    'exit_code' => $exitCode,
                    'timeout' => false,
                ];
            }

            // Check if it's a compile error (stderr contains compile-like errors)
            if ($profile->isCompiled() && $this->looksLikeCompileError($stderr)) {
                return [
                    'status' => 'compile_error',
                    'stdout' => $stdout,
                    'stderr' => $stderr,
                    'exit_code' => $exitCode,
                    'timeout' => false,
                ];
            }

            return [
                'status' => 'runtime_error',
                'stdout' => $stdout,
                'stderr' => $stderr,
                'exit_code' => $exitCode,
                'timeout' => false,
            ];

        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'timed out')) {
                return [
                    'status' => 'timeout',
                    'stdout' => '',
                    'stderr' => 'Execution timed out after ' . $timeout . ' seconds',
                    'exit_code' => null,
                    'timeout' => true,
                ];
            }

            return [
                'status' => 'system_error',
                'stdout' => '',
                'stderr' => $e->getMessage(),
                'exit_code' => null,
                'timeout' => false,
            ];
        }
    }

    /**
     * Check if stderr looks like a compilation error.
     */
    private function looksLikeCompileError(string $stderr): bool
    {
        $compilePatterns = [
            '/error:/i',
            '/Error:/i',
            '/ERROR:/i',
            '/fatal error:/i',
            '/compilation failed/i',
            '/syntax error/i',
            '/undefined reference/i',
            '/ld returned/i',
        ];

        foreach ($compilePatterns as $pattern) {
            if (preg_match($pattern, $stderr)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse execution result into standardized format.
     */
    private function parseExecutionResult(array $result, LanguageExecutionProfile $profile, int $executionDurationMs): array
    {
        $status = $result['status'];
        $stdout = $result['stdout'] ?? '';
        $stderr = $result['stderr'] ?? '';
        $exitCode = $result['exit_code'];
        $timeout = $result['timeout'] ?? false;

        $compileError = null;
        $runtimeError = null;

        if ($status === 'compile_error') {
            $compileError = $stderr;
        } elseif ($status === 'runtime_error') {
            $runtimeError = $stderr;
        } elseif ($status === 'timeout') {
            $runtimeError = 'Execution timed out';
        }

        return [
            'status' => $status,
            'stdout' => $this->truncateOutput($stdout),
            'stderr' => $this->truncateOutput($stderr),
            'compile_error' => $compileError ? $this->truncateOutput($compileError) : null,
            'runtime_error' => $runtimeError ? $this->truncateOutput($runtimeError) : null,
            'timeout' => $timeout,
            'exit_code' => $exitCode,
            'execution_duration_ms' => $executionDurationMs,
            'memory_used_kb' => null, // Would need docker stats for accurate measurement
            'resource_usage' => [],
            'test_summary' => null,
        ];
    }

    /**
     * Create failure result.
     */
    private function failureResult(CodeExecution $execution, string $message, string $status): array
    {
        return [
            'status' => $status,
            'stdout' => '',
            'stderr' => $message,
            'compile_error' => $status === 'compile_error' ? $message : null,
            'runtime_error' => $status === 'runtime_error' ? $message : null,
            'timeout' => $status === 'timeout',
            'exit_code' => null,
            'execution_duration_ms' => 0,
            'memory_used_kb' => null,
            'resource_usage' => [],
            'test_summary' => null,
        ];
    }

    /**
     * Truncate output to prevent excessive storage.
     */
    private function truncateOutput(string $output): string
    {
        $maxBytes = self::MAX_OUTPUT_SIZE_KB * 1024;
        if (strlen($output) > $maxBytes) {
            return substr($output, 0, $maxBytes) . "\n... [OUTPUT TRUNCATED]";
        }
        return $output;
    }

    /**
     * Clean up Docker container.
     */
    private function cleanupContainer(string $containerName): void
    {
        try {
            Process::timeout(5)->run("docker rm -f {$containerName} 2>/dev/null || true");
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
    }
}