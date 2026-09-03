<?php

namespace App\Providers;

use App\Contracts\Research\ReassessmentCandidateGenerator;
use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\ActivitySubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningMaterial;
use App\Models\LearningProgress;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Policies\ActivityProgressPolicy;
use App\Policies\ActivitySubmissionPolicy;
use App\Policies\CoursePolicy;
use App\Policies\EnrollmentPolicy;
use App\Policies\LearningMaterialPolicy;
use App\Policies\LearningProgressPolicy;
use App\Policies\LearningUnitPolicy;
use App\Policies\ModulePolicy;
use App\Services\Ai\AiClientManager;
use App\Services\Ai\Prompts\ReassessmentPromptBuilder;
use App\Services\Research\Reassessment\DeterministicReassessmentCandidateGenerator;
use App\Services\Research\Reassessment\LlmReassessmentCandidateGenerator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->runningUnitTests()) {
            $this->app->bind(
                ReassessmentCandidateGenerator::class,
                DeterministicReassessmentCandidateGenerator::class,
            );
        } else {
            $this->app->bind(ReassessmentCandidateGenerator::class, function () {
                return new LlmReassessmentCandidateGenerator(
                    new AiClientManager(),
                    new ReassessmentPromptBuilder(),
                    new DeterministicReassessmentCandidateGenerator(),
                );
            });
        }
    }


    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Course::class, CoursePolicy::class);
        Gate::policy(Module::class, ModulePolicy::class);
        Gate::policy(LearningUnit::class, LearningUnitPolicy::class);
        Gate::policy(LearningMaterial::class, LearningMaterialPolicy::class);
        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(ActivityProgress::class, ActivityProgressPolicy::class);
        Gate::policy(ActivitySubmission::class, ActivitySubmissionPolicy::class);
        Gate::policy(Enrollment::class, EnrollmentPolicy::class);
        Gate::policy(LearningProgress::class, LearningProgressPolicy::class);

        Gate::define('updateOwnedContent', function (User $user, User $owner): bool {
            return $user->isTutor() && $user->is($owner);
        });
    }
}
