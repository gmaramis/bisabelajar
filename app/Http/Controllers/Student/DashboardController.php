<?php

namespace App\Http\Controllers\Student;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $enrollments = $request->user()
            ->enrollments()
            ->where('status', EnrollmentStatus::Active)
            ->with('course')
            ->latest('enrolled_at')
            ->get();

        return view('student.dashboard', [
            'user' => $request->user(),
            'enrollments' => $enrollments,
        ]);
    }
}
