<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LearningController extends Controller
{
    public function index(Request $request): View
    {
        $enrollments = $request->user()
            ->enrollments()
            ->with('course')
            ->latest('enrolled_at')
            ->get();

        return view('student.learning', [
            'user' => $request->user(),
            'enrollments' => $enrollments,
        ]);
    }
}
