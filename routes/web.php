<?php

use App\Http\Controllers\AiDisclosureIconController;
use App\Http\Controllers\ApplicationPdfController;
use App\Http\Controllers\CvPageController;
use App\Http\Controllers\CvPdfController;
use App\Http\Controllers\CvPrivateDataController;
use App\Http\Controllers\CvPrivateLinkController;
use App\Http\Controllers\CvPrivateSignatureController;
use App\Http\Controllers\CvTokenExchangeController;
use App\Http\Controllers\SearchDocumentsController;
use App\Http\Middleware\ApplicationAccess;
use Illuminate\Support\Facades\Route;
use Statamic\Facades\Antlers;
use Statamic\View\View;

// Route::statamic('example', 'example-view', [
//    'title' => 'Example'
// ]);

Route::get('/sitemap.xml', [SearchDocumentsController::class, 'sitemap'])->name('sitemap');
Route::get('/llms.txt', [SearchDocumentsController::class, 'llms'])->name('llms');
Route::get('/llms-full.txt', [SearchDocumentsController::class, 'llmsFull'])->name('llms.full');
Route::get('/media/ai-disclosures/{kind}/{appearance}.svg', AiDisclosureIconController::class)
    ->where([
        'kind' => 'basic|fully-generated|partially-modified',
        'appearance' => 'black|white|black-50|white-50',
    ])
    ->name('media.ai-disclosure-icon');

Route::get('/design-system', function () {
    abort_unless(app()->environment(['local', 'testing']) && config('design-system.enabled'), 404);

    return response(
        (new View)
            ->template('lab/index')
            ->layout('lab/layout')
            ->render()
    )->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->header('Cache-Control', 'private, no-store');
})->name('design-system');

Route::get('/__preview/media-credits', function () {
    abort_unless(app()->environment(['local', 'testing']), 404);

    $partial = file_get_contents(resource_path('views/partials/media/overlays.antlers.html'));
    $samples = [
        [
            'title' => 'Project hero · Erstwählerforum Stade',
            'page' => '/projekte/erstwaehlerforum-stade/',
            'image' => '/assets/projects/erstwaehlerforum-stade/img-20260602-wa0030.jpg',
            'alt' => 'Blick von der Bühne auf das Publikum im großen Saal.',
            'frame' => 'media-preview__frame--wide',
            'icon_appearance' => 'white-50',
            'ai_involvement' => 'partially_modified',
            'credit_appearance' => 'dark',
            'credit' => 'Foto: Erstwählerforum Stade',
            'rights' => 'cc',
        ],
        [
            'title' => 'Education · Athenaeum Stade',
            'page' => '/cv/edu/gymnasium-athenaeum-stade/',
            'image' => '/assets/cv/education/gymnasium-athenaeum-stade/exterior_of_the_athenaeum_stade.jpg',
            'alt' => 'Athenaeum Stade',
            'frame' => 'media-preview__frame--wide media-preview__frame--school',
            'icon_appearance' => 'black-50',
            'ai_involvement' => 'basic_disclosure',
            'credit_appearance' => 'dark',
            'credit' => 'Von Marvin Ruder - Eigenes Werk, CC BY-SA 3.0, https://commons.wikimedia.org/w/index.php?curid=30528076',
            'rights' => 'cc',
        ],
        [
            'title' => 'CV · Portrait',
            'page' => '/cv/',
            'image' => '/assets/jack/profile_pictures/jack-20251020.jpg',
            'alt' => 'Jack Ruder',
            'frame' => 'media-preview__frame--portrait',
            'icon_appearance' => 'white',
            'ai_involvement' => 'fully_generated',
            'credit_appearance' => 'dark',
            'credit' => 'Jack Ruder · CC BY-SA 4.0',
            'rights' => 'cc',
            'portrait' => true,
        ],
        [
            'title' => 'Project hero · AI-CTX code image',
            'page' => '/projekte/ai-ctx/',
            'image' => '/assets/projects/ai-ctx/effect-ai-ctx.png',
            'alt' => 'TypeScript-Code definiert den Befehl ai-ctx und seine Unterbefehle.',
            'frame' => 'media-preview__frame--code',
            'icon_appearance' => 'black',
            'ai_involvement' => 'partially_modified',
            'credit_appearance' => 'light',
            'credit' => 'Quelle: ai-ctx',
            'rights' => 'cc',
        ],
    ];

    $samples = array_map(function (array $sample) use ($partial): array {
        $sample['overlay'] = (string) Antlers::parse($partial, [
            'asset' => [],
            'ai_involvement' => $sample['ai_involvement'],
            'eu_ai_disclosure' => 'Vorschau-Fixture; keine Aussage über diese Datei.',
            'ai_icon_appearance' => $sample['icon_appearance'],
            'credit_appearance' => $sample['credit_appearance'],
            'credit_override' => $sample['credit'],
            'rights_override' => $sample['rights'],
        ]);

        return $sample;
    }, $samples);

    return response()->view('previews.media-credits', ['samples' => $samples])
        ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->header('Cache-Control', 'private, no-store');
})->name('preview.media-credits');

Route::get('/cv/signature-example', [\App\Http\Controllers\ApplicationHandwrittenSignatureController::class, 'exampleImage'])->name('cv.signature-example');

Route::post('/cv/private-data', [CvPrivateDataController::class, 'reveal'])
    ->name('cv.private-data');
Route::post('/cv/private-signature', CvPrivateSignatureController::class)
    ->middleware('throttle:6,1')
    ->name('cv.private-signature');
Route::post('/cv/token-exchange', CvTokenExchangeController::class)
    ->middleware('throttle:12,1')
    ->name('cv.token-exchange');

Route::get('/cv/pdf', CvPdfController::class)
    ->middleware('throttle:6,1')
    ->name('cv.pdf');

Route::get('/cv/{profile}/pdf', CvPdfController::class)
    ->where('profile', '[a-z0-9-]+')
    ->middleware('throttle:6,1')
    ->name('cv.profile.pdf');

Route::post('/cp/cv/private-link/temporary', [CvPrivateLinkController::class, 'temporary'])
    ->middleware(['statamic.cp', 'statamic.cp.authenticated'])
    ->name('cv.private-link.temporary');
Route::post('/cp/cv/private-link/permanent', [CvPrivateLinkController::class, 'permanent'])
    ->middleware(['statamic.cp', 'statamic.cp.authenticated'])
    ->name('cv.private-link.permanent');
Route::delete('/cp/cv/private-link/permanent', [CvPrivateLinkController::class, 'revoke'])
    ->middleware(['statamic.cp', 'statamic.cp.authenticated'])
    ->name('cv.private-link.revoke');
Route::get('/cp/cv/private-link/grants', [CvPrivateLinkController::class, 'grants'])->middleware(['statamic.cp', 'statamic.cp.authenticated'])->name('cv.private-link.grants');
Route::post('/cp/cv/private-link/grants', [CvPrivateLinkController::class, 'issueGrant'])->middleware(['statamic.cp', 'statamic.cp.authenticated'])->name('cv.private-link.grants.issue');
Route::delete('/cp/cv/private-link/grants/{grantId}', [CvPrivateLinkController::class, 'revokeGrant'])->middleware(['statamic.cp', 'statamic.cp.authenticated'])->whereNumber('grantId')->name('cv.private-link.grants.revoke');

Route::statamic('/projekte', 'projects/index', ['title' => 'Projekte']);
Route::statamic('/blog', 'posts/index', ['title' => 'Blog']);
Route::statamic('/publikationen', 'publications/index', ['title' => 'Publikationen']);
Route::statamic('/aktuell', 'now/index', ['title' => 'Aktuell']);
Route::get('/cv', [CvPageController::class, 'show'])->name('cv.show');
Route::get('/cv/{profile}', [CvPageController::class, 'profile'])
    ->where('profile', '[a-z0-9-]+')
    ->name('cv.profile');

Route::get('/cp/cv/private-link/status', [CvPrivateLinkController::class, 'status'])->middleware(['statamic.cp', 'statamic.cp.authenticated']);
Route::get('/cp/cv/private-link/qr', [CvPrivateLinkController::class, 'qr'])->middleware(['statamic.cp', 'statamic.cp.authenticated']);

Route::prefix('applications')->middleware(ApplicationAccess::class)->group(function () {
    Route::get('{id}/cv/pdf', [CvPageController::class, 'applicationPdf'])->whereUuid('id')->middleware('throttle:application-pdf')->name('applications.cv.pdf');
    Route::get('{id}/cv', [CvPageController::class, 'application'])->whereUuid('id')->name('applications.cv');
    Route::post('token-exchange', [ApplicationPdfController::class, 'exchange'])->middleware('throttle:application-exchange')->name('applications.exchange');
    Route::post('analytics/page-view', [ApplicationPdfController::class, 'trackPageView'])->middleware('throttle:120,1')->name('applications.analytics.page-view');
    Route::get('{id}/pdf', [ApplicationPdfController::class, 'recipient'])->whereUuid('id')->middleware('throttle:application-pdf')->name('applications.pdf');
    Route::get('{id}', [ApplicationPdfController::class, 'landing'])->whereUuid('id')->name('applications.access');
});
