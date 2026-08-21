<?php

namespace App\Services\Execution;

use App\Models\LanguageExecutionProfile;
use Illuminate\Support\Collection;

class LanguageExecutionProfileService
{
    /**
     * Get all enabled profiles for student-facing selection.
     */
    public function getEnabledProfiles(): Collection
    {
        return LanguageExecutionProfile::query()
            ->where('enabled', true)
            ->orderBy('display_name')
            ->get();
    }

    /**
     * Get a profile by identifier.
     */
    public function getProfileByIdentifier(string $identifier): ?LanguageExecutionProfile
    {
        return LanguageExecutionProfile::query()
            ->where('identifier', $identifier)
            ->where('enabled', true)
            ->first();
    }

    /**
     * Get a profile by ID.
     */
    public function getProfileById(int $id): ?LanguageExecutionProfile
    {
        return LanguageExecutionProfile::query()
            ->where('id', $id)
            ->where('enabled', true)
            ->first();
    }

    /**
     * Get profiles available for a specific programming activity.
     */
    public function getProfilesForActivity(int $programmingActivityId): Collection
    {
        $programmingActivity = \App\Models\ProgrammingActivity::find($programmingActivityId);
        
        if (! $programmingActivity) {
            return collect();
        }

        // If activity has a specific profile, only return that one
        if ($programmingActivity->language_execution_profile_id) {
            return $this->getEnabledProfiles()->where('id', $programmingActivity->language_execution_profile_id);
        }

        // Otherwise return all enabled profiles
        return $this->getEnabledProfiles();
    }

    /**
     * Validate that a profile is available for use.
     */
    public function validateProfile(int $profileId): bool
    {
        return $this->getProfileById($profileId) !== null;
    }

    /**
     * Get the default profile (Python).
     */
    public function getDefaultProfile(): ?LanguageExecutionProfile
    {
        return $this->getProfileByIdentifier('python');
    }

    /**
     * Get profiles grouped by execution mode.
     */
    public function getProfilesByMode(): array
    {
        $profiles = $this->getEnabledProfiles();
        
        return [
            'compiled' => $profiles->where('execution_mode', 'compiled')->values(),
            'interpreted' => $profiles->where('execution_mode', 'interpreted')->values(),
        ];
    }
}