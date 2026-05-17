<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Driver;
use App\Models\Student;
use App\Observers\UserObserver;
use App\Observers\DriverObserver;
use App\Observers\StudentObserver;
use App\Http\Guards\BearerTokenGuard;

class AppServiceProvider extends ServiceProvider {
    public function register(): void {}
    public function boot(): void {
        User::observe(UserObserver::class);
        Driver::observe(DriverObserver::class);
        Student::observe(StudentObserver::class);
        
        // Register custom Bearer token guard
        Auth::extend('bearer_token', function ($app, $name, array $config) {
            return new BearerTokenGuard(
                Auth::createUserProvider($config['provider']),
                $app['request']
            );
        });
    }
}
