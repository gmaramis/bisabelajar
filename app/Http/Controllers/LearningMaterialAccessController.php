<?php

namespace App\Http\Controllers;

use App\Enums\MaterialType;
use App\Models\Course;
use App\Models\LearningMaterial;
use App\Models\LearningUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LearningMaterialAccessController extends Controller
{
    /**
     * Viewing material does not record progress or mastery.
     */
    public function show(
        Request $request,
        Course $course,
        LearningUnit $learningUnit,
        LearningMaterial $material,
    ): View {
        $this->ensurePublishedAccess($course, $learningUnit, $material);
        $this->authorize('view', $material);

        return view('materials.show', [
            'course' => $course,
            'learningUnit' => $learningUnit,
            'material' => $material,
        ]);
    }

    /**
     * File access is authorization-gated. PowerPoint is downloaded, not rendered.
     */
    public function download(
        Request $request,
        Course $course,
        LearningUnit $learningUnit,
        LearningMaterial $material,
    ): StreamedResponse {
        $this->ensurePublishedAccess($course, $learningUnit, $material);
        $this->authorize('view', $material);
        abort_unless($material->isFile() && $material->disk && $material->file_path, 404);
        abort_unless(Storage::disk($material->disk)->exists($material->file_path), 404);

        $disposition = $material->type === MaterialType::Pdf ? 'inline' : 'attachment';

        return Storage::disk($material->disk)->response(
            $material->file_path,
            $material->downloadName(),
            [
                'Content-Disposition' => $disposition.'; filename="'.$material->downloadName().'"',
            ],
        );
    }

    private function ensurePublishedAccess(
        Course $course,
        LearningUnit $learningUnit,
        LearningMaterial $material,
    ): void {
        abort_unless($learningUnit->module?->course_id === $course->id, 404);
        abort_unless($material->learning_unit_id === $learningUnit->id, 404);
    }
}
