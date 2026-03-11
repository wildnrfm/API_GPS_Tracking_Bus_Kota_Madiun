<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\User;
use App\Models\Driver;
use App\Models\Student;
use App\Observers\UserObserver;
use App\Observers\DriverObserver;
use App\Observers\StudentObserver;

class AppServiceProvider extends ServiceProvider {
    public function register(): void {}
    public function boot(): void {
        User::observe(UserObserver::class);
        Driver::observe(DriverObserver::class);
        Student::observe(StudentObserver::class);
    }
}
