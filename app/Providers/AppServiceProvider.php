<?php

namespace App\Providers;

use App\Fieldtypes\ProtectedText;
use App\Tags\HomepageProjects;
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
        ProtectedText::register();
        HomepageProjects::register();
    }
}
