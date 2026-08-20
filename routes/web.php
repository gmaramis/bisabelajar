<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CourseCatalogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Student\LearningController;
use App\Http\Controllers\Tutor\CourseController;
use App\Http\Controllers\Tutor\ModuleController;
use App\Http\Controllers\Tutor\OwnedContentController;
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

    Route::middleware('role:student')->group(function (): void {
        Route::get('my-learning', [LearningController::class, 'index'])->name('student.learning');
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
    });
});
