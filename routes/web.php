<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Student\LearningController;
use App\Http\Controllers\Tutor\OwnedContentController;
use App\Http\Controllers\Tutor\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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
    });
});
