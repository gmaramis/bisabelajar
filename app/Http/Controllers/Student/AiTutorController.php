<?php

namespace App\Http\Controllers\Student;

use App\Exceptions\Ai\AllProvidersFailedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\AiHintRequest;
use App\Models\Activity;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Services\Ai\NexusSocraticTutorService;
use Illuminate\Http\JsonResponse;

class AiTutorController extends Controller
{
    public function __construct(
        private readonly NexusSocraticTutorService $tutor,
    ) {}

    public function hint(
        AiHintRequest $request,
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
        Activity $activity,
    ): JsonResponse {
        $student = $request->user();

        $enrolled = Enrollment::query()
            ->where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->where('status', 'active')
            ->exists();

        if (! $enrolled) {
            return response()->json([
                'message' => 'You are not enrolled in this course.',
            ], 403);
        }

        if ($activity->status?->value !== 'published') {
            return response()->json(['message' => 'Activity not available.'], 404);
        }

        try {
            $result = $this->tutor->hint($activity, $request->attemptContext());

            return response()->json([
                'hint' => $result['hint'],
                'response_type' => $result['response_type'],
                'advisory_only' => true,
                'provider' => $result['provider'],
                'model' => $result['model'],
            ]);
        } catch (AllProvidersFailedException) {
            return response()->json([
                'hint' => 'NEXUS sedang tidak tersedia saat ini. Coba periksa dokumentasi atau diskusikan dengan tutor.',
                'response_type' => 'fallback',
                'advisory_only' => true,
            ], 503);
        }
    }
}
