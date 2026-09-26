<?php

namespace App\Providers;

use App\Console\Commands\CheckProductionReadiness;
use App\Contracts\LocalSecretStore;
use App\Contracts\PayMongoClient;
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
use App\Services\Payments\PayMongoApiClient;
use App\Support\PublicHttps;
use App\Support\WindowsDpapiSecretStore;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LocalSecretStore::class, WindowsDpapiSecretStore::class);

        // The live client is the default. Tests bind a fake so the payment
        // state machine is verifiable without credentials.
        $this->app->bind(PayMongoClient::class, PayMongoApiClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->commands([CheckProductionReadiness::class]);

        $this->forceHttpsWhenTheAppUrlIsHttps();

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ActivityLog::class, ActivityLogPolicy::class);
        Gate::policy(Course::class, CoursePolicy::class);
        Gate::policy(Module::class, ModulePolicy::class);
        Gate::policy(Lesson::class, LessonPolicy::class);
        Gate::policy(LearningMaterial::class, LearningMaterialPolicy::class);
        Gate::policy(Enrollment::class, EnrollmentPolicy::class);
    }

    /**
     * Generate https URLs when the public address is https.
     *
     * A TLS-terminating proxy forwards plain HTTP, so the request looks like
     * http unless the proxy sends X-Forwarded-Proto and the proxy address is
     * trusted. Not every proxy does both, and when it does not, every generated
     * URL carries http. A browser then refuses the stylesheet and the scripts
     * as mixed content, and the page arrives unstyled.
     *
     * The decision comes from APP_URL rather than from the request, so it is
     * explicit and cannot be influenced by a header. It is also why APP_URL has
     * to be set to the real public address on a deployed server. The transport
     * security header reads the same answer through PublicHttps.
     */
    private function forceHttpsWhenTheAppUrlIsHttps(): void
    {
        if (PublicHttps::isEnabled()) {
            URL::forceScheme('https');
        }
    }
}
