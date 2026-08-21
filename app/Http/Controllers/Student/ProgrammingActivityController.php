<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Course;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\ProgrammingActivity;
use App\Services\Execution\ProgrammingActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\RateLimiter;

class ProgrammingActivityController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('role:student'),
        ];
    }

    public function __construct(
        private ProgrammingActivityService $programmingActivityService
    ) {}

    /**
     * Run code in the sandbox (Run button).
     */
    public function run(
        Request $request,
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
        Activity $activity
    ): JsonResponse {
        $this->ensureAccess($course, $module, $learningUnit, $activity);

        $user = $request->user();
        $programmingActivity = $activity->programmingActivity;

        if (! $programmingActivity) {
            return response()->json([
                'success' => false,
                'error' => 'This activity is not a programming activity.',
            ], 400);
        }

        // Rate limiting: 30 runs per minute per user per activity
        $rateLimitKey = "programming_run:{$user->id}:{$activity->id}";
        if (RateLimiter::tooManyAttempts($rateLimitKey, 30)) {
            return response()->json([
                'success' => false,
                'error' => 'Too many execution requests. Please wait before running again.',
                'retry_after' => RateLimiter::availableIn($rateLimitKey),
            ], 429);
        }

        $request->validate([
            'source_code' => 'required|string|max:100000',
            'language_execution_profile_id' => 'nullable|integer|exists:language_execution_profiles,id',
        ]);

        RateLimiter::hit($rateLimitKey, 60);

        $result = $this->programmingActivityService->runCode(
            $user,
            $programmingActivity,
            $request->string('source_code')->toString(),
            $request->integer('language_execution_profile_id')
        );

        if (! $result['success']) {
            return response()->json($result, 400);
        }

        return response()->json([
            'success' => true,
            'execution' => [
                'id' => $result['execution_id'],
                'status' => $result['status'],
                'stdout' => $result['stdout'],
                'stderr' => $result['stderr'],
                'exit_code' => $result['exit_code'],
                'duration_ms' => $result['execution_duration_ms'],
                'memory_used_mb' => round(($result['memory_used_kb'] ?? 0) / 1024, 2),
            ],
        ]);
    }

    /**
     * Submit code for formal evaluation (Submit button).
     */
    public function submit(
        Request $request,
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
        Activity $activity
    ): JsonResponse {
        $this->ensureAccess($course, $module, $learningUnit, $activity);

        $user = $request->user();
        $programmingActivity = $activity->programmingActivity;

        if (! $programmingActivity) {
            return response()->json([
                'success' => false,
                'error' => 'This activity is not a programming activity.',
            ], 400);
        }

        // Get enrollment
        $enrollment = \App\Models\Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', \App\Enums\EnrollmentStatus::Active)
            ->first();

        if (! $enrollment) {
            return response()->json([
                'success' => false,
                'error' => 'No active enrollment found for this course.',
            ], 403);
        }

        // Check if activity has been started
        $progress = $user->activityProgress()
            ->where('activity_id', $activity->id)
            ->first();

        if (! $progress || $progress->status->value === 'not_started') {
            return response()->json([
                'success' => false,
                'error' => 'You must start this activity before submitting.',
            ], 403);
        }

        // Check remaining attempts
        $submissionsCount = $user->activitySubmissions()
            ->where('activity_id', $activity->id)
            ->count();

        if ($submissionsCount >= $activity->maxAttempts()) {
            return response()->json([
                'success' => false,
                'error' => 'No remaining submission attempts.',
            ], 403);
        }

        // Rate limiting: 10 submissions per hour per user per activity
        $rateLimitKey = "programming_submit:{$user->id}:{$activity->id}";
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            return response()->json([
                'success' => false,
                'error' => 'Too many submission attempts. Please wait before submitting again.',
                'retry_after' => RateLimiter::availableIn($rateLimitKey),
            ], 429);
        }

        $request->validate([
            'source_code' => 'required|string|max:100000',
            'language_execution_profile_id' => 'nullable|integer|exists:language_execution_profiles,id',
        ]);

        RateLimiter::hit($rateLimitKey, 3600);

        $result = $this->programmingActivityService->submitCode(
            $user,
            $programmingActivity,
            $request->string('source_code')->toString(),
            $request->integer('language_execution_profile_id')
        );

        if (! $result['success']) {
            return response()->json($result, 400);
        }

        // Create ActivitySubmission record for formal tracking
        $submission = $user->activitySubmissions()->create([
            'enrollment_id' => $enrollment->id,
            'activity_id' => $activity->id,
            'attempt_number' => $submissionsCount + 1,
            'payload' => [
                'source_code' => $request->string('source_code')->toString(),
                'language_execution_profile_id' => $request->integer('language_execution_profile_id'),
                'execution_id' => $result['execution_id'],
            ],
            'status' => $result['passes_evaluation'] ? \App\Enums\SubmissionStatus::Accepted : \App\Enums\SubmissionStatus::Rejected,
            'version' => $result['execution_id'],
            'submitted_at' => now(),
        ]);

        // Log submission event
        \App\Models\LearningEvent::record(
            $result['passes_evaluation'] ? 'SUBMISSION_ACCEPTED' : 'SUBMISSION_REJECTED',
            $user->id,
            $course->id,
            $activity->id,
            [
                'submission_id' => $submission->id,
                'attempt_number' => $submission->attempt_number,
                'passes_evaluation' => $result['passes_evaluation'],
                'test_summary' => $result['test_summary'],
            ]
        );

        return response()->json([
            'success' => true,
            'submission' => [
                'id' => $submission->id,
                'status' => $submission->status->value,
                'score' => $result['passes_evaluation'] ? 100 : 0,
                'passed_tests' => ($result['test_summary']['passed'] ?? 0),
                'total_tests' => ($result['test_summary']['total'] ?? 0),
                'test_results' => collect($result['test_summary']['details'] ?? [])->map(fn($d) => [
                    'test_case_id' => $d['test_case_id'] ?? null,
                    'passed' => $d['passed'] ?? false,
                    'stdout' => $d['actual_output'] ?? null,
                    'stderr' => $d['actual_error'] ?? null,
                    'duration_ms' => $d['execution_duration_ms'] ?? null,
                    'memory_used_mb' => round(($d['memory_used_kb'] ?? 0) / 1024, 2),
                ])->toArray(),
            ],
        ]);
    }

    /**
     * Get activity data including starter code and available profiles.
     */
    public function show(
        Request $request,
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
        Activity $activity
    ): JsonResponse {
        $this->ensureAccess($course, $module, $learningUnit, $activity);

        $programmingActivity = $activity->programmingActivity;

        if (! $programmingActivity) {
            return response()->json([
                'success' => false,
                'error' => 'This activity is not a programming activity.',
            ], 400);
        }

        $profiles = $programmingActivity->language_execution_profile_id
            ? collect([$programmingActivity->languageExecutionProfile])
            : \App\Models\LanguageExecutionProfile::where('enabled', true)->get();

        return response()->json([
            'success' => true,
            'activity' => [
                'id' => $activity->id,
                'title' => $activity->title,
                'type' => $activity->type->value,
                'configuration' => $activity->studentSafeConfiguration(),
            ],
            'programming_activity' => [
                'id' => $programmingActivity->id,
                'starter_code' => $this->programmingActivityService->getStarterCode($programmingActivity),
                'editable_files' => $this->programmingActivityService->getEditableFiles($programmingActivity),
                'execution_time_limit_seconds' => $programmingActivity->getExecutionTimeLimitSeconds(),
                'memory_limit_mb' => $programmingActivity->getMemoryLimitMb(),
                'source_code_size_limit_kb' => $programmingActivity->getSourceCodeSizeLimitKb(),
                'language_execution_profile_id' => $programmingActivity->language_execution_profile_id,
            ],
            'available_profiles' => $profiles->map(fn($p) => [
                'id' => $p->id,
                'identifier' => $p->identifier,
                'display_name' => $p->display_name,
                'file_extension' => $p->file_extension,
                'source_filename' => $p->source_filename,
                'execution_mode' => $p->execution_mode,
            ]),
        ]);
    }

    /**
     * Get execution history for this activity.
     */
    public function history(
        Request $request,
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
        Activity $activity
    ): JsonResponse {
        $this->ensureAccess($course, $module, $learningUnit, $activity);

        $user = $request->user();

        $executions = \App\Models\CodeExecution::query()
            ->where('user_id', $user->id)
            ->where('activity_id', $activity->id)
            ->with('languageExecutionProfile')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'executions' => $executions->map(fn($e) => [
                'id' => $e->id,
                'status' => $e->status,
                'language' => $e->languageExecutionProfile?->identifier,
                'stdout' => $e->stdout,
                'stderr' => $e->stderr,
                'compile_error' => $e->compile_error,
                'runtime_error' => $e->runtime_error,
                'timeout' => $e->timeout,
                'exit_code' => $e->exit_code,
                'execution_duration_ms' => $e->execution_duration_ms,
                'test_summary' => $e->test_summary,
                'created_at' => $e->created_at->toISOString(),
            ]),
        ]);
    }

    private function ensureAccess(Course $course, Module $module, LearningUnit $learningUnit, Activity $activity): void
    {
        abort_unless($module->course_id === $course->id, 404);
        abort_unless($learningUnit->module_id === $module->id, 404);
        abort_unless($activity->learning_unit_id === $learningUnit->id, 404);
        abort_unless($activity->isPublished(), 404);
        $this->authorize('view', $activity);
    }
}