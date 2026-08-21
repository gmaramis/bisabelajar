<?php

namespace App\Services\Execution;

use App\Models\CodeExecution;
use App\Models\LanguageExecutionProfile;
use App\Models\ProgrammingActivity;
use App\Models\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ProgrammingActivityService
{
    private DockerExecutionService $dockerExecutionService;

    public function __construct(DockerExecutionService $dockerExecutionService)
    {
        $this->dockerExecutionService = $dockerExecutionService;
    }

    /**
     * Execute code for a programming activity (Run button).
     */
    public function runCode(
        User $user,
        ProgrammingActivity $programmingActivity,
        string $sourceCode,
        ?int $languageExecutionProfileId = null
    ): array {
        // Verify the profile is allowed for this activity
        $profile = $this->resolveProfile($programmingActivity, $languageExecutionProfileId);
        
        if (! $profile) {
            return [
                'success' => false,
                'error' => 'Invalid or disabled language profile',
                'status' => 'system_error',
            ];
        }

        // Validate source code size
        if (strlen($sourceCode) > $programmingActivity->getSourceCodeSizeLimitKb() * 1024) {
            return [
                'success' => false,
                'error' => 'Source code exceeds size limit of ' . $programmingActivity->getSourceCodeSizeLimitKb() . ' KB',
                'status' => 'resource_limit',
            ];
        }

        // Get visible test cases for immediate feedback
        $testCases = $programmingActivity->visibleTestCases()
            ->get()
            ->map(fn($tc) => $tc->toArray())
            ->toArray();

        // Create execution record
        $execution = CodeExecution::create([
            'user_id' => $user->id,
            'course_id' => $programmingActivity->activity->learningUnit->module->course_id,
            'activity_id' => $programmingActivity->activity_id,
            'programming_activity_id' => $programmingActivity->id,
            'language_execution_profile_id' => $profile->id,
            'status' => 'running',
            'source_code' => $sourceCode,
        ]);

        // Execute in Docker
        $result = $this->dockerExecutionService->execute(
            $execution,
            $sourceCode,
            $profile,
            null,
            $testCases
        );

        // Update execution record
        $execution->update([
            'status' => $result['status'],
            'stdout' => $result['stdout'],
            'stderr' => $result['stderr'],
            'compile_error' => $result['compile_error'],
            'runtime_error' => $result['runtime_error'],
            'timeout' => $result['timeout'],
            'exit_code' => $result['exit_code'],
            'execution_duration_ms' => $result['execution_duration_ms'],
            'memory_used_kb' => $result['memory_used_kb'],
            'resource_usage' => $result['resource_usage'],
            'test_summary' => $result['test_summary'],
        ]);

        // Log learning event
        $this->logLearningEvent($user, $programmingActivity, 'code_run', [
            'execution_id' => $execution->id,
            'status' => $result['status'],
            'language' => $profile->identifier,
            'has_tests' => !empty($testCases),
        ]);

        return [
            'success' => true,
            'execution_id' => $execution->id,
            'status' => $result['status'],
            'stdout' => $result['stdout'],
            'stderr' => $result['stderr'],
            'compile_error' => $result['compile_error'],
            'runtime_error' => $result['runtime_error'],
            'timeout' => $result['timeout'],
            'exit_code' => $result['exit_code'],
            'execution_duration_ms' => $result['execution_duration_ms'],
            'test_summary' => $result['test_summary'],
        ];
    }

    /**
     * Submit code for formal evaluation (Submit button).
     */
    public function submitCode(
        User $user,
        ProgrammingActivity $programmingActivity,
        string $sourceCode,
        ?int $languageExecutionProfileId = null
    ): array {
        // Verify the profile is allowed
        $profile = $this->resolveProfile($programmingActivity, $languageExecutionProfileId);
        
        if (! $profile) {
            return [
                'success' => false,
                'error' => 'Invalid or disabled language profile',
                'status' => 'system_error',
            ];
        }

        // Validate source code size
        if (strlen($sourceCode) > $programmingActivity->getSourceCodeSizeLimitKb() * 1024) {
            return [
                'success' => false,
                'error' => 'Source code exceeds size limit',
                'status' => 'resource_limit',
            ];
        }

        // Get ALL test cases (visible + hidden) for submission
        $testCases = $programmingActivity->testCases()
            ->get()
            ->map(fn($tc) => $tc->toArray())
            ->toArray();

        // Create execution record linked to submission
        $execution = CodeExecution::create([
            'user_id' => $user->id,
            'course_id' => $programmingActivity->activity->learningUnit->module->course_id,
            'activity_id' => $programmingActivity->activity_id,
            'programming_activity_id' => $programmingActivity->id,
            'language_execution_profile_id' => $profile->id,
            'status' => 'running',
            'source_code' => $sourceCode,
        ]);

        // Execute with all tests
        $result = $this->dockerExecutionService->execute(
            $execution,
            $sourceCode,
            $profile,
            null,
            $testCases
        );

        // Determine if submission passes evaluation
        $evaluationConfig = $programmingActivity->getEvaluationConfig();
        $passesEvaluation = $this->evaluateSubmission($result['test_summary'] ?? [], $evaluationConfig);

        // Update execution record
        $execution->update([
            'status' => $result['status'],
            'stdout' => $result['stdout'],
            'stderr' => $result['stderr'],
            'compile_error' => $result['compile_error'],
            'runtime_error' => $result['runtime_error'],
            'timeout' => $result['timeout'],
            'exit_code' => $result['exit_code'],
            'execution_duration_ms' => $result['execution_duration_ms'],
            'memory_used_kb' => $result['memory_used_kb'],
            'resource_usage' => $result['resource_usage'],
            'test_summary' => $result['test_summary'],
        ]);

        // Log learning events - action event and outcome event
        $this->logLearningEvent($user, $programmingActivity, 'code_submit', [
            'execution_id' => $execution->id,
            'status' => $result['status'],
            'language' => $profile->identifier,
            'passes_evaluation' => $passesEvaluation,
            'test_summary' => $result['test_summary'],
        ]);

        return [
            'success' => true,
            'execution_id' => $execution->id,
            'passes_evaluation' => $passesEvaluation,
            'status' => $result['status'],
            'language' => $profile->identifier,
            'stdout' => $result['stdout'],
            'stderr' => $result['stderr'],
            'compile_error' => $result['compile_error'],
            'runtime_error' => $result['runtime_error'],
            'timeout' => $result['timeout'],
            'exit_code' => $result['exit_code'],
            'execution_duration_ms' => $result['execution_duration_ms'],
            'test_summary' => $result['test_summary'],
        ];
    }

    /**
     * Record the canonical M3 outcome event after ActivitySubmission exists
     * so validation runs once with complete provenance.
     */
    public function recordSubmissionOutcome(
        User $user,
        ProgrammingActivity $programmingActivity,
        array $submitResult,
        \App\Models\ActivitySubmission $submission
    ): \App\Models\LearningEvent {
        $outcomeEventType = ($submitResult['passes_evaluation'] ?? false)
            ? 'submission_accepted'
            : 'submission_rejected';

        return \App\Models\LearningEvent::record(
            $outcomeEventType,
            $user->id,
            $programmingActivity->activity->learningUnit->module->course_id,
            $programmingActivity->activity_id,
            [
                'execution_id' => $submitResult['execution_id'] ?? null,
                'status' => $submitResult['status'] ?? null,
                'language' => $submitResult['language'] ?? null,
                'passes_evaluation' => $submitResult['passes_evaluation'] ?? null,
                'test_summary' => $submitResult['test_summary'] ?? null,
                'submission_id' => $submission->id,
                'attempt_number' => $submission->attempt_number,
            ]
        );
    }

    /**
     * Get starter code for an activity.
     */
    public function getStarterCode(ProgrammingActivity $programmingActivity): string
    {
        return $programmingActivity->starter_code ?? '';
    }

    /**
     * Get editable files configuration.
     */
    public function getEditableFiles(ProgrammingActivity $programmingActivity): array
    {
        return $programmingActivity->getEditableFiles();
    }

    /**
     * Resolve language execution profile for an activity.
     */
    private function resolveProfile(ProgrammingActivity $programmingActivity, ?int $requestedProfileId): ?LanguageExecutionProfile
    {
        // If activity has a fixed profile, use it
        if ($programmingActivity->language_execution_profile_id) {
            return LanguageExecutionProfile::find($programmingActivity->language_execution_profile_id);
        }

        // Otherwise use requested profile if valid
        if ($requestedProfileId) {
            $profile = LanguageExecutionProfile::find($requestedProfileId);
            if ($profile && $profile->enabled) {
                return $profile;
            }
        }

        // Fall back to default
        return LanguageExecutionProfile::where('identifier', 'python')
            ->where('enabled', true)
            ->first();
    }

    /**
     * Evaluate submission against test results.
     */
    private function evaluateSubmission(array $testSummary, array $evaluationConfig): bool
    {
        if (empty($testSummary) || $testSummary['total'] === 0) {
            // No tests configured - pass if execution succeeded
            return true;
        }

        $visibleTotal = $testSummary['visible_total'] ?? 0;
        $visiblePassed = $testSummary['visible_passed'] ?? 0;
        $hiddenTotal = $testSummary['hidden_total'] ?? 0;
        $hiddenPassed = $testSummary['hidden_passed'] ?? 0;

        $passThreshold = $evaluationConfig['pass_threshold'] ?? 1.0;
        $requireAllHidden = $evaluationConfig['require_all_hidden'] ?? true;

        // Check visible tests threshold
        if ($visibleTotal > 0) {
            $visibleRatio = $visiblePassed / $visibleTotal;
            if ($visibleRatio < $passThreshold) {
                return false;
            }
        }

        // Check hidden tests
        if ($requireAllHidden && $hiddenTotal > 0) {
            if ($hiddenPassed < $hiddenTotal) {
                return false;
            }
        }

        return true;
    }

    /**
     * Log learning event.
     */
    private function logLearningEvent(User $user, ProgrammingActivity $programmingActivity, string $eventType, array $payload): void
    {
        \App\Models\LearningEvent::record(
            $eventType,
            $user->id,
            $programmingActivity->activity->learningUnit->module->course_id,
            $programmingActivity->activity_id,
            $payload
        );
    }
}