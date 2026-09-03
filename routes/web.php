<?php

use App\Http\Controllers\ActivityAccessController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CourseCatalogController;
use App\Http\Controllers\LearningMaterialAccessController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Student\ActivityCompletionController;
use App\Http\Controllers\Student\ActivityStartController;
use App\Http\Controllers\Student\ActivitySubmissionController;
use App\Http\Controllers\Student\CourseExperienceController;
use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\EnrollmentController;
use App\Http\Controllers\Student\LearningController;
use App\Http\Controllers\Student\LearningProgressController;
use App\Http\Controllers\Student\ProgrammingActivityController as StudentProgrammingActivityController;
use App\Http\Controllers\Student\AiTutorController;
use App\Http\Controllers\Tutor\ActivityController;
use App\Http\Controllers\Tutor\CourseController;
use App\Http\Controllers\Tutor\LearningMaterialController;
use App\Http\Controllers\Tutor\LearningUnitController;
use App\Http\Controllers\Tutor\ModuleController;
use App\Http\Controllers\Tutor\OwnedContentController;
use App\Http\Controllers\Tutor\ProgrammingActivityController as TutorProgrammingActivityController;
use App\Http\Controllers\Tutor\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('courses', [CourseCatalogController::class, 'index'])->name('courses.index');
Route::get('courses/{course:slug}', [CourseCatalogController::class, 'show'])->name('courses.show');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('users/{user}', [ProfileController::class, 'showUser'])->name('users.show');

    Route::get('courses/{course:slug}/units/{learningUnit}/materials/{material}', [LearningMaterialAccessController::class, 'show'])
        ->name('materials.show');
    Route::get('courses/{course:slug}/units/{learningUnit}/materials/{material}/file', [LearningMaterialAccessController::class, 'download'])
        ->name('materials.download');
    Route::get('courses/{course:slug}/units/{learningUnit}/activities/{activity}', [ActivityAccessController::class, 'show'])
        ->name('activities.show');

    Route::middleware('role:student')->group(function (): void {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('student.dashboard');
        Route::get('my-courses', [LearningController::class, 'index'])->name('student.courses');
        Route::get('my-courses/{course:slug}', [CourseExperienceController::class, 'showCourse'])->name('student.courses.show');
        Route::get('my-courses/{course:slug}/modules/{module}', [CourseExperienceController::class, 'showModule'])->name('student.modules.show');
        Route::get('my-courses/{course:slug}/modules/{module}/units/{learningUnit}', [CourseExperienceController::class, 'showUnit'])->name('student.units.show');
        Route::post('my-courses/{course:slug}/modules/{module}/units/{learningUnit}/complete', [LearningProgressController::class, 'complete'])->name('student.progress.complete');
        Route::post('my-courses/{course:slug}/modules/{module}/units/{learningUnit}/activities/{activity}/start', [ActivityStartController::class, 'start'])->name('student.activities.start');
        Route::post('my-courses/{course:slug}/modules/{module}/units/{learningUnit}/activities/{activity}/submissions', [ActivitySubmissionController::class, 'store'])->name('student.activities.submit');
        Route::post('my-courses/{course:slug}/modules/{module}/units/{learningUnit}/activities/{activity}/complete', [ActivityCompletionController::class, 'complete'])->name('student.activities.complete');
        Route::post('courses/{course:slug}/enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');

        // M3 Programming Activity Routes
        Route::get('my-courses/{course:slug}/modules/{module}/units/{learningUnit}/activities/{activity}/programming', [StudentProgrammingActivityController::class, 'show'])->name('student.activities.programming.show');
        Route::post('my-courses/{course:slug}/modules/{module}/units/{learningUnit}/activities/{activity}/programming/run', [StudentProgrammingActivityController::class, 'run'])->name('student.activities.programming.run');
        Route::post('my-courses/{course:slug}/modules/{module}/units/{learningUnit}/activities/{activity}/programming/submit', [StudentProgrammingActivityController::class, 'submit'])->name('student.activities.programming.submit');
        Route::get('my-courses/{course:slug}/modules/{module}/units/{learningUnit}/activities/{activity}/programming/history', [StudentProgrammingActivityController::class, 'history'])->name('student.activities.programming.history');
        Route::post('my-courses/{course:slug}/modules/{module}/units/{learningUnit}/activities/{activity}/ai-hint', [AiTutorController::class, 'hint'])->name('student.activities.ai-hint');
    });

    Route::middleware('role:tutor')->prefix('tutor')->name('tutor.')->group(function (): void {
        Route::get('/', [WorkspaceController::class, 'index'])->name('workspace');
        Route::patch('owned-content/{owner}', [OwnedContentController::class, 'update'])
            ->name('owned-content.update');

        Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
        Route::get('courses/create', [CourseController::class, 'create'])->name('courses.create');
        Route::post('courses', [CourseController::class, 'store'])->name('courses.store');
        Route::get('courses/{course}/edit', [CourseController::class, 'edit'])->name('courses.edit');
        Route::put('courses/{course}', [CourseController::class, 'update'])->name('courses.update');
        Route::post('courses/{course}/publish', [CourseController::class, 'publish'])->name('courses.publish');
        Route::post('courses/{course}/archive', [CourseController::class, 'archive'])->name('courses.archive');

        Route::get('courses/{course}/modules/create', [ModuleController::class, 'create'])->name('modules.create');
        Route::post('courses/{course}/modules', [ModuleController::class, 'store'])->name('modules.store');
        Route::post('courses/{course}/modules/reorder', [ModuleController::class, 'reorder'])->name('modules.reorder');
        Route::get('courses/{course}/modules/{module}/edit', [ModuleController::class, 'edit'])->name('modules.edit');
        Route::put('courses/{course}/modules/{module}', [ModuleController::class, 'update'])->name('modules.update');
        Route::delete('courses/{course}/modules/{module}', [ModuleController::class, 'destroy'])->name('modules.destroy');
        Route::post('courses/{course}/modules/{module}/publish', [ModuleController::class, 'publish'])->name('modules.publish');
        Route::post('courses/{course}/modules/{module}/unpublish', [ModuleController::class, 'unpublish'])->name('modules.unpublish');

        Route::get('courses/{course}/modules/{module}/units/create', [LearningUnitController::class, 'create'])->name('units.create');
        Route::post('courses/{course}/modules/{module}/units', [LearningUnitController::class, 'store'])->name('units.store');
        Route::post('courses/{course}/modules/{module}/units/reorder', [LearningUnitController::class, 'reorder'])->name('units.reorder');
        Route::get('courses/{course}/modules/{module}/units/{learningUnit}/edit', [LearningUnitController::class, 'edit'])->name('units.edit');
        Route::put('courses/{course}/modules/{module}/units/{learningUnit}', [LearningUnitController::class, 'update'])->name('units.update');
        Route::delete('courses/{course}/modules/{module}/units/{learningUnit}', [LearningUnitController::class, 'destroy'])->name('units.destroy');
        Route::post('courses/{course}/modules/{module}/units/{learningUnit}/publish', [LearningUnitController::class, 'publish'])->name('units.publish');
        Route::post('courses/{course}/modules/{module}/units/{learningUnit}/unpublish', [LearningUnitController::class, 'unpublish'])->name('units.unpublish');

        Route::get('courses/{course}/modules/{module}/units/{learningUnit}/materials/create', [LearningMaterialController::class, 'create'])->name('materials.create');
        Route::post('courses/{course}/modules/{module}/units/{learningUnit}/materials', [LearningMaterialController::class, 'store'])->name('materials.store');
        Route::post('courses/{course}/modules/{module}/units/{learningUnit}/materials/reorder', [LearningMaterialController::class, 'reorder'])->name('materials.reorder');
        Route::delete('courses/{course}/modules/{module}/units/{learningUnit}/materials/{material}', [LearningMaterialController::class, 'destroy'])->name('materials.destroy');
        Route::post('courses/{course}/modules/{module}/units/{learningUnit}/materials/{material}/publish', [LearningMaterialController::class, 'publish'])->name('materials.publish');
        Route::post('courses/{course}/modules/{module}/units/{learningUnit}/materials/{material}/unpublish', [LearningMaterialController::class, 'unpublish'])->name('materials.unpublish');

        Route::get('courses/{course}/modules/{module}/units/{learningUnit}/activities/create', [ActivityController::class, 'create'])->name('activities.create');
        Route::post('courses/{course}/modules/{module}/units/{learningUnit}/activities', [ActivityController::class, 'store'])->name('activities.store');
        Route::post('courses/{course}/modules/{module}/units/{learningUnit}/activities/reorder', [ActivityController::class, 'reorder'])->name('activities.reorder');
        Route::get('courses/{course}/modules/{module}/units/{learningUnit}/activities/{activity}/edit', [ActivityController::class, 'edit'])->name('activities.edit');
        Route::put('courses/{course}/modules/{module}/units/{learningUnit}/activities/{activity}', [ActivityController::class, 'update'])->name('activities.update');
        Route::post('courses/{course}/modules/{module}/units/{learningUnit}/activities/{activity}/publish', [ActivityController::class, 'publish'])->name('activities.publish');
        Route::post('courses/{course}/modules/{module}/units/{learningUnit}/activities/{activity}/unpublish', [ActivityController::class, 'unpublish'])->name('activities.unpublish');
        Route::post('courses/{course}/modules/{module}/units/{learningUnit}/activities/{activity}/archive', [ActivityController::class, 'archive'])->name('activities.archive');

        // M3 Programming Activity Tutor Routes
        Route::get('courses/{course}/modules/{module}/units/{learningUnit}/activities/{activity}/programming', [TutorProgrammingActivityController::class, 'show'])->name('activities.programming.show');
        Route::post('courses/{course}/modules/{module}/units/{learningUnit}/activities/{activity}/programming', [TutorProgrammingActivityController::class, 'store'])->name('activities.programming.store');
        Route::put('courses/{course}/modules/{module}/units/{learningUnit}/activities/{activity}/programming', [TutorProgrammingActivityController::class, 'update'])->name('activities.programming.update');

        // Test Case Routes
        Route::get('courses/{course}/modules/{module}/units/{learningUnit}/activities/{activity}/test-cases', [TutorProgrammingActivityController::class, 'testCases'])->name('activities.test-cases.index');
        Route::post('courses/{course}/modules/{module}/units/{learningUnit}/activities/{activity}/test-cases', [TutorProgrammingActivityController::class, 'storeTestCase'])->name('activities.test-cases.store');
        Route::put('courses/{course}/modules/{module}/units/{learningUnit}/activities/{activity}/test-cases/{testCase}', [TutorProgrammingActivityController::class, 'updateTestCase'])->name('activities.test-cases.update');
        Route::delete('courses/{course}/modules/{module}/units/{learningUnit}/activities/{activity}/test-cases/{testCase}', [TutorProgrammingActivityController::class, 'destroyTestCase'])->name('activities.test-cases.destroy');
        Route::post('courses/{course}/modules/{module}/units/{learningUnit}/activities/{activity}/test-cases/reorder', [TutorProgrammingActivityController::class, 'reorderTestCases'])->name('activities.test-cases.reorder');
    });
});
