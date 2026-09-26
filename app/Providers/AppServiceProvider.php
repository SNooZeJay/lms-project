<?php

namespace App\Providers;

use App\Contracts\LocalSecretStore;
use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningMaterial;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Policies\ActivityLogPolicy;
use App\Policies\CoursePolicy;
use App\Policies\EnrollmentPolicy;
use App\Policies\LearningMaterialPolicy;
use App\Policies\LessonPolicy;
use App\Policies\ModulePolicy;
use App\Policies\UserPolicy;
use App\Support\WindowsDpapiSecretStore;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LocalSecretStore::class, WindowsDpapiSecretStore::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ActivityLog::class, ActivityLogPolicy::class);
        Gate::policy(Course::class, CoursePolicy::class);
        Gate::policy(Module::class, ModulePolicy::class);
        Gate::policy(Lesson::class, LessonPolicy::class);
        Gate::policy(LearningMaterial::class, LearningMaterialPolicy::class);
        Gate::policy(Enrollment::class, EnrollmentPolicy::class);
    }
}
