<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // ML Training permissions
        Gate::define('viewMLTraining', function ($user) {
            return in_array($user->role, ['counselor', 'admin']);
        });

        Gate::define('validateMLPredictions', function ($user) {
            return in_array($user->role, ['counselor', 'admin']);
        });

        Gate::define('exportMLData', function ($user) {
            return in_array($user->role, ['counselor', 'admin']);
        });

        Gate::define('trainMLModel', function ($user) {
            return $user->role === 'admin';
        });
    }
}
