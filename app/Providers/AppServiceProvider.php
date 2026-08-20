<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\LearningMaterial;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\User;
use App\Policies\CoursePolicy;
use App\Policies\LearningMaterialPolicy;
use App\Policies\LearningUnitPolicy;
use App\Policies\ModulePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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

        Gate::define('updateOwnedContent', function (User $user, User $owner): bool {
            return $user->isTutor() && $user->is($owner);
        });
    }
}
