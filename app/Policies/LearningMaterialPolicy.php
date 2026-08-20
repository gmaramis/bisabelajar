<?php

namespace App\Policies;

use App\Models\LearningMaterial;
use App\Models\LearningUnit;
use App\Models\User;

class LearningMaterialPolicy
{
    public function view(User $user, LearningMaterial $material): bool
    {
        if ($user->can('update', $material->learningUnit)) {
            return true;
        }

        $unit = $material->learningUnit;

        return $material->isPublished() && $user->can('learn', $unit);
    }

    public function create(User $user, LearningUnit $learningUnit): bool
    {
        return $user->can('update', $learningUnit);
    }

    public function update(User $user, LearningMaterial $material): bool
    {
        return $user->can('update', $material->learningUnit);
    }

    public function delete(User $user, LearningMaterial $material): bool
    {
        return $this->update($user, $material);
    }

    public function reorder(User $user, LearningUnit $learningUnit): bool
    {
        return $user->can('update', $learningUnit);
    }

    public function publish(User $user, LearningMaterial $material): bool
    {
        return $this->update($user, $material) && $material->canBePublished();
    }

    public function unpublish(User $user, LearningMaterial $material): bool
    {
        return $this->update($user, $material);
    }
}
