<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Course;
use App\Models\LanguageExecutionProfile;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\ProgrammingActivity;
use App\Models\TestCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class ProgrammingActivityController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
            new Middleware('role:tutor'),
        ];
    }

    /**
     * Get programming activity details for tutor.
     */
    public function show(
        Request $request,
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
        Activity $activity
    ): JsonResponse {
        $this->ensureOwnership($course, $module, $learningUnit, $activity);

        $programmingActivity = $activity->programmingActivity()->with('languageExecutionProfile')->first();

        return response()->json([
            'success' => true,
            'activity' => [
                'id' => $activity->id,
                'title' => $activity->title,
                'type' => $activity->type->value,
                'configuration' => $activity->configuration,
            ],
            'programming_activity' => $programmingActivity ? [
                'id' => $programmingActivity->id,
                'language_execution_profile_id' => $programmingActivity->language_execution_profile_id,
                'starter_code' => $programmingActivity->starter_code,
                'editable_files' => $programmingActivity->editable_files,
                'execution_time_limit_seconds' => $programmingActivity->execution_time_limit_seconds,
                'memory_limit_mb' => $programmingActivity->memory_limit_mb,
                'source_code_size_limit_kb' => $programmingActivity->source_code_size_limit_kb,
                'submission_rules' => $programmingActivity->submission_rules,
                'evaluation_config' => $programmingActivity->evaluation_config,
                'language_execution_profile' => $programmingActivity->languageExecutionProfile,
            ] : null,
            'available_profiles' => LanguageExecutionProfile::where('enabled', true)->get(['id', 'identifier', 'display_name']),
        ]);
    }

    /**
     * Create programming activity configuration.
     */
    public function store(
        Request $request,
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
        Activity $activity
    ): JsonResponse {
        $this->ensureOwnership($course, $module, $learningUnit, $activity);

        abort_unless($activity->type === \App\Enums\ActivityType::CodingExercise, 400, 'Activity must be a CodingExercise type.');

        $request->validate([
            'language_execution_profile_id' => 'required|integer|exists:language_execution_profiles,id',
            'starter_code' => 'nullable|string|max:100000',
            'editable_files' => 'nullable|array',
            'editable_files.*' => 'string|max:255',
            'execution_time_limit_seconds' => 'nullable|integer|min:1|max:300',
            'memory_limit_mb' => 'nullable|integer|min:64|max:2048',
            'source_code_size_limit_kb' => 'nullable|integer|min:1|max:1000',
            'submission_rules' => 'nullable|array',
            'evaluation_config' => 'nullable|array',
        ]);

        $programmingActivity = ProgrammingActivity::create([
            'activity_id' => $activity->id,
            'language_execution_profile_id' => $request->integer('language_execution_profile_id'),
            'starter_code' => $request->string('starter_code')->toString(),
            'editable_files' => $request->input('editable_files'),
            'execution_time_limit_seconds' => $request->integer('execution_time_limit_seconds'),
            'memory_limit_mb' => $request->integer('memory_limit_mb'),
            'source_code_size_limit_kb' => $request->integer('source_code_size_limit_kb'),
            'submission_rules' => $request->input('submission_rules'),
            'evaluation_config' => $request->input('evaluation_config'),
        ]);

        // Update activity configuration with language from profile
        $profile = LanguageExecutionProfile::find($request->integer('language_execution_profile_id'));
        $activity->update([
            'configuration' => array_merge($activity->configuration, [
                'language' => $profile->identifier,
            ]),
        ]);

        return response()->json([
            'success' => true,
            'programming_activity' => $programmingActivity->load('languageExecutionProfile'),
        ], 201);
    }

    /**
     * Update programming activity configuration.
     */
    public function update(
        Request $request,
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
        Activity $activity
    ): JsonResponse {
        $this->ensureOwnership($course, $module, $learningUnit, $activity);

        $programmingActivity = $activity->programmingActivity;
        abort_unless($programmingActivity, 404, 'Programming activity not found.');

        $request->validate([
            'language_execution_profile_id' => 'sometimes|integer|exists:language_execution_profiles,id',
            'starter_code' => 'nullable|string|max:100000',
            'editable_files' => 'nullable|array',
            'editable_files.*' => 'string|max:255',
            'execution_time_limit_seconds' => 'nullable|integer|min:1|max:300',
            'memory_limit_mb' => 'nullable|integer|min:64|max:2048',
            'source_code_size_limit_kb' => 'nullable|integer|min:1|max:1000',
            'submission_rules' => 'nullable|array',
            'evaluation_config' => 'nullable|array',
        ]);

        $programmingActivity->update($request->only([
            'language_execution_profile_id',
            'starter_code',
            'editable_files',
            'execution_time_limit_seconds',
            'memory_limit_mb',
            'source_code_size_limit_kb',
            'submission_rules',
            'evaluation_config',
        ]));

        // Update activity configuration
        if ($request->has('language_execution_profile_id')) {
            $profile = LanguageExecutionProfile::find($request->integer('language_execution_profile_id'));
            $activity->update([
                'configuration' => array_merge($activity->configuration, [
                    'language' => $profile->identifier,
                ]),
            ]);
        }

        return response()->json([
            'success' => true,
            'programming_activity' => $programmingActivity->load('languageExecutionProfile'),
        ]);
    }

    /**
     * List test cases for a programming activity.
     */
    public function testCases(
        Request $request,
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
        Activity $activity
    ): JsonResponse {
        $this->ensureOwnership($course, $module, $learningUnit, $activity);

        $programmingActivity = $activity->programmingActivity;
        abort_unless($programmingActivity, 404, 'Programming activity not found.');

        $testCases = $programmingActivity->testCases()->orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'test_cases' => $testCases,
        ]);
    }

    /**
     * Create a test case.
     */
    public function storeTestCase(
        Request $request,
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
        Activity $activity
    ): JsonResponse {
        $this->ensureOwnership($course, $module, $learningUnit, $activity);

        $programmingActivity = $activity->programmingActivity;
        abort_unless($programmingActivity, 404, 'Programming activity not found.');

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'input' => 'nullable|string',
            'expected_output' => 'required|string',
            'visible' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'timeout_seconds' => 'nullable|integer|min:1|max:60',
            'memory_limit_mb' => 'nullable|integer|min:64|max:2048',
            'comparison_strategy' => ['nullable', Rule::in(['exact', 'contains', 'regex', 'trimmed'])],
            'metadata' => 'nullable|array',
        ]);

        $testCase = $programmingActivity->testCases()->create([
            'name' => $request->string('name')->toString(),
            'description' => $request->string('description')->toString(),
            'input' => $request->string('input')->toString(),
            'expected_output' => $request->string('expected_output')->toString(),
            'visible' => $request->boolean('visible', true),
            'sort_order' => $request->integer('sort_order', $programmingActivity->testCases()->max('sort_order') + 1),
            'timeout_seconds' => $request->integer('timeout_seconds'),
            'memory_limit_mb' => $request->integer('memory_limit_mb'),
            'comparison_strategy' => $request->string('comparison_strategy')->toString() ?? 'exact',
            'metadata' => $request->input('metadata'),
        ]);

        return response()->json([
            'success' => true,
            'test_case' => $testCase,
        ], 201);
    }

    /**
     * Update a test case.
     */
    public function updateTestCase(
        Request $request,
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
        Activity $activity,
        TestCase $testCase
    ): JsonResponse {
        $this->ensureOwnership($course, $module, $learningUnit, $activity);

        abort_unless($testCase->programming_activity_id === $activity->programmingActivity?->id, 404);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'input' => 'nullable|string',
            'expected_output' => 'sometimes|string',
            'visible' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'timeout_seconds' => 'nullable|integer|min:1|max:60',
            'memory_limit_mb' => 'nullable|integer|min:64|max:2048',
            'comparison_strategy' => ['nullable', Rule::in(['exact', 'contains', 'regex', 'trimmed'])],
            'metadata' => 'nullable|array',
        ]);

        $testCase->update($request->only([
            'name',
            'description',
            'input',
            'expected_output',
            'visible',
            'sort_order',
            'timeout_seconds',
            'memory_limit_mb',
            'comparison_strategy',
            'metadata',
        ]));

        return response()->json([
            'success' => true,
            'test_case' => $testCase->fresh(),
        ]);
    }

    /**
     * Delete a test case.
     */
    public function destroyTestCase(
        Request $request,
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
        Activity $activity,
        TestCase $testCase
    ): JsonResponse {
        $this->ensureOwnership($course, $module, $learningUnit, $activity);

        abort_unless($testCase->programming_activity_id === $activity->programmingActivity?->id, 404);

        $testCase->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Reorder test cases.
     */
    public function reorderTestCases(
        Request $request,
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
        Activity $activity
    ): JsonResponse {
        $this->ensureOwnership($course, $module, $learningUnit, $activity);

        $programmingActivity = $activity->programmingActivity;
        abort_unless($programmingActivity, 404, 'Programming activity not found.');

        $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:test_cases,id',
        ]);

        foreach ($request->array('order') as $index => $testCaseId) {
            $programmingActivity->testCases()->where('id', $testCaseId)->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }

    private function ensureOwnership(Course $course, Module $module, LearningUnit $learningUnit, Activity $activity): void
    {
        abort_unless($module->course_id === $course->id, 404);
        abort_unless($learningUnit->module_id === $module->id, 404);
        abort_unless($activity->learning_unit_id === $learningUnit->id, 404);
        $this->authorize('update', $activity);
    }
}