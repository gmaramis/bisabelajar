<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    public function create(User $user, Course $course): bool
    {
        return $user->isStudent() && $course->isEnrollable();
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        if ($user->is($enrollment->user)) {
            return true;
        }

        return $user->isTutor() && $enrollment->course->isOwnedBy($user);
    }

    public function viewAnyForCourse(User $user, Course $course): bool
    {
        return $user->isTutor() && $course->isOwnedBy($user);
    }
}
