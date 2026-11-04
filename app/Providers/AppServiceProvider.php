<?php

namespace App\Providers;

use App\Fieldtypes\ProtectedText;
use App\Tags\CvTitle;
use App\Tags\HomepageProjects;
use App\Tags\SearchMetadata;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Statamic\Facades\CP\Nav;
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
        SearchMetadata::register();
        Statamic::externalScript(asset('/cp-cv-profile-access.js'));

        Statamic::pushCpRoutes(function () {
            Route::namespace('App\\Http\\Controllers')->group(function () {
                require base_path('routes/cp.php');
            });
        });

        Nav::extend(function ($nav) {
            $nav->create('Search Agent Optimization')
                ->section('Tools')
                ->route('search-agent-optimization.index')
                ->icon('search');
        });
    }
}
