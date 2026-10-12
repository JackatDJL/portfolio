<?php

namespace App\Providers;

use App\Fieldtypes\ProtectedText;
use App\Tags\CvTitle;
use App\Tags\HomepageProjects;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Statamic\Statamic;

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
        if (app()->environment('production') && parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https') {
            URL::forceScheme('https');
        }

        ProtectedText::register();
        CvTitle::register();
        HomepageProjects::register();
        Statamic::externalScript(asset('/cp-cv-profile-access.js'));
    }
}
