<?php

namespace Tests\Feature;

use App\Support\CvCapabilities;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Session\FileSessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProductionDeploymentTest extends TestCase
{
    public function test_dokploy_build_watch_paths_exclude_content_and_asset_payloads_but_include_app_and_schema_code(): void
    {
        $patterns = json_decode(file_get_contents(base_path('docker/dokploy-watch-paths.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertFalse($this->matchesAny($patterns, 'content/collections/projects/example.md'));
        $this->assertFalse($this->matchesAny($patterns, 'content/assets/assets.yaml'));
        $this->assertFalse($this->matchesAny($patterns, 'public/assets/photos/portrait.jpg'));
        $this->assertFalse($this->matchesAny($patterns, 'public/documents/paper.pdf'));
        $this->assertFalse($this->matchesAny($patterns, 'users/jack@djl.foundation.yaml'));
        $this->assertTrue($this->matchesAny($patterns, 'Dockerfile'));
        $this->assertTrue($this->matchesAny($patterns, 'app/Support/CvCapabilities.php'));
        $this->assertTrue($this->matchesAny($patterns, 'resources/blueprints/collections/projects/project.yaml'));
        $this->assertTrue($this->matchesAny($patterns, 'public/cp-cv-profile-access.js'));

        $dockerignore = file_get_contents(base_path('.dockerignore'));
        foreach (['content/**', 'public/assets/**', 'public/documents/**', 'users/**', '.env.*'] as $ignoredPath) {
            $this->assertStringContainsString($ignoredPath, $dockerignore);
        }
    }

    public function test_laravel_health_route_boots_without_exposing_application_state(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
        $this->assertStringNotContainsString((string) config('app.key'), $response->getContent());
        $this->assertStringNotContainsString('cv_access_tokens', $response->getContent());
    }

    public function test_permanent_cv_grants_and_sessions_survive_reopening_persistent_storage(): void
    {
        $directory = storage_path('framework/testing/deployment-state-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($directory, 0700, true);
        $database = $directory.'/portfolio.sqlite';
        touch($database);

        $oldDatabase = config('database.connections.sqlite.database');
        $oldDefault = config('database.default');
        $oldSessionPath = config('session.files');
        $sessionPath = $directory.'/sessions';
        File::ensureDirectoryExists($sessionPath, 0700, true);

        try {
            config()->set('database.default', 'sqlite');
            config()->set('database.connections.sqlite.database', $database);
            DB::purge('sqlite');
            $this->assertSame(0, Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true, '--no-interaction' => true]));

            $capability = CvCapabilities::permanent('/cv/jobmesse-26');
            $webauthnTable = config('statamic.users.tables.webauthn', 'webauthn');
            DB::table($webauthnTable)->insert([
                'id' => 'deployment-test-passkey',
                'user_id' => 'jack@djl.foundation',
                'name' => 'Persistent test passkey',
                'credential' => json_encode(['fixture' => true], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $sessionId = str_repeat('a', 40);
            $handler = new FileSessionHandler(app(Filesystem::class), $sessionPath, 120);
            $session = new Store('portfolio_test', $handler);
            $session->setId($sessionId);
            $session->start();
            $session->put('cv_grants.'.hash('sha256', '/cv/jobmesse-26'), hash('sha256', $capability['token']));
            $session->save();

            DB::disconnect('sqlite');
            DB::purge('sqlite');
            config()->set('session.files', $sessionPath);

            $reopenedSession = new Store('portfolio_test', $handler);
            $reopenedSession->setId($sessionId);
            $reopenedSession->start();
            $this->assertSame(
                hash('sha256', $capability['token']),
                $reopenedSession->get('cv_grants.'.hash('sha256', '/cv/jobmesse-26')),
            );
            $request = Request::create('/cv/jobmesse-26');
            $request->setLaravelSession($reopenedSession);

            $this->assertSame($capability['token'], CvCapabilities::permanent('/cv/jobmesse-26')['token']);
            $this->assertTrue(CvCapabilities::allows($capability['token'], '/cv/jobmesse-26'));
            $this->assertTrue(CvCapabilities::sessionAllows($request, '/cv/jobmesse-26'));
            $this->assertSame(hash('sha256', $capability['token']), DB::table('cv_access_tokens')->value('token_hash'));
            $this->assertSame('Persistent test passkey', DB::table($webauthnTable)->where('id', 'deployment-test-passkey')->value('name'));
        } finally {
            DB::disconnect('sqlite');
            DB::purge('sqlite');
            config()->set('database.connections.sqlite.database', $oldDatabase);
            config()->set('database.default', $oldDefault);
            config()->set('session.files', $oldSessionPath);
            File::deleteDirectory($directory);
        }
    }

    private function matchesAny(array $patterns, string $path): bool
    {
        foreach ($patterns as $pattern) {
            $regex = preg_quote($pattern, '/');
            $regex = str_replace('\\*\\*', '.*', $regex);
            $regex = str_replace('\\*', '[^/]*', $regex);
            if (preg_match('#^'.$regex.'$#D', $path) === 1) {
                return true;
            }
        }

        return false;
    }
}
