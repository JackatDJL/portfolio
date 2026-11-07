<?php

namespace App\Providers;

use App\Fieldtypes\ApplicationPrivateGrid;
use App\Fieldtypes\ApplicationTextComponent;
use App\Fieldtypes\ProtectedText;
use App\Support\ApplicationPdfPostProcessor;
use App\Support\ApplicationPrivacy;
use App\Support\SearchPath;
use App\Support\UnmodifiedApplicationPdf;
use App\Tags\CvTitle;
use App\Tags\HomepageProjects;
use App\Tags\SearchMetadata;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Statamic\Facades\CP\Nav;
use Statamic\Statamic;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ApplicationPdfPostProcessor::class, UnmodifiedApplicationPdf::class);
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
        ApplicationPrivateGrid::register();
        ApplicationTextComponent::register();
        ApplicationPrivacy::boot();
        RateLimiter::for('application-pdf', fn (Request $request) => Limit::perMinute(6)->by($request->user()?->getAuthIdentifier() ?: $request->ip()));
        RateLimiter::for('application-exchange', fn (Request $request) => Limit::perMinute(12)->by($request->ip()));
        CvTitle::register();
        HomepageProjects::register();
        SearchMetadata::register();
        Validator::extend(
            'noindex_path_list',
            fn ($attribute, $value) => SearchPath::isValidList($value),
            ':attribute muss gültige absolute Pfade wie /cv enthalten; nur / darf mit einem Schrägstrich enden.',
        );
        Validator::extend('https_url_list', function ($attribute, $value): bool {
            if (! is_array($value)) {
                return false;
            }

            foreach ($value as $url) {
                if (! is_string($url)
                    || filter_var($url, FILTER_VALIDATE_URL) === false
                    || ! preg_match('#^https://#i', $url)) {
                    return false;
                }
            }

            return true;
        }, ':attribute darf nur gültige öffentliche HTTPS-URLs enthalten.');
        Statamic::externalScript(asset('/cp-cv-profile-access.js'));
        Statamic::externalScript(asset('/cp-applications.js'));

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
