<?php

namespace App\Providers;

use App\Fieldtypes\ApplicationPrivateGrid;
use App\Fieldtypes\ApplicationPrivateGroup;
use App\Fieldtypes\ApplicationTextComponent;
use App\Fieldtypes\ProtectedText;
use App\Signatures\ApplicationCompositeSource;
use App\Signatures\LocalPdfSignatureValidator;
use App\Signatures\SandboxSignatureProvider;
use App\Signatures\SignatureProvider;
use App\Signatures\SignatureValidator;
use App\Signatures\SignatureWorkflowException;
use App\Signatures\UnavailableSignatureProvider;
use App\Support\ApplicationPdfExporter;
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
use Illuminate\Support\Facades\Vite;
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
        $this->app->bind(ApplicationCompositeSource::class, ApplicationPdfExporter::class);
        $this->app->bind(SignatureValidator::class, LocalPdfSignatureValidator::class);
        $this->app->bind(SignatureProvider::class, function () {
            return match (config('signatures.provider')) {
                'unavailable' => new UnavailableSignatureProvider,
                'sandbox' => new SandboxSignatureProvider,
                default => throw new SignatureWorkflowException('Unbekannter Signaturanbieter.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($hotFile = env('HOMEPAGE_PREVIEW_HOT_FILE')) {
            Vite::useHotFile($hotFile);
        }

        if (app()->environment('production') && parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https') {
            URL::forceScheme('https');
        }

        ProtectedText::register();
        ApplicationPrivateGrid::register();
        ApplicationPrivateGroup::register();
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
        Statamic::externalScript(asset('/cp-applications.js').'?v='.hash_file('sha256', public_path('cp-applications.js')));
        Statamic::externalScript(asset('/cp-handwritten-signature.js').'?v='.hash_file('sha256', public_path('cp-handwritten-signature.js')));
        Statamic::externalStyle(asset('/cp-applications.css').'?v='.hash_file('sha256', public_path('cp-applications.css')));
        Statamic::externalStyle(asset('/cp-sponsorships.css').'?v='.hash_file('sha256', public_path('cp-sponsorships.css')));

        Statamic::pushCpRoutes(function () {
            Route::namespace('App\\Http\\Controllers')->group(function () {
                require base_path('routes/cp.php');
            });
        });

        Nav::extend(function ($nav) {
            $nav->create('Sponsoring und Förderung')
                ->section('Tools')
                ->route('sponsorships.index')
                ->icon('money');
            $nav->create('Search Agent Optimization')
                ->section('Tools')
                ->route('search-agent-optimization.index')
                ->icon('search');
        });
    }
}
