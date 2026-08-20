<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isTutor();
    }

    public function view(User $user, Course $course): bool
    {
        if ($course->isOwnedBy($user)) {
            return true;
        }

        return $course->isPubliclyViewable();
    }

    public function create(User $user): bool
    {
        return $user->isTutor();
    }

    public function update(User $user, Course $course): bool
    {
        return $user->isTutor() && $course->isOwnedBy($user);
    }

    public function publish(User $user, Course $course): bool
    {
        return $this->update($user, $course);
    }

    public function archive(User $user, Course $course): bool
    {
        return $this->update($user, $course);
    }
}
