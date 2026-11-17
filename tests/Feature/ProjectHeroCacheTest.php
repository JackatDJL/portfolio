<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Statamic\Facades\StaticCache;
use Statamic\StaticCaching\Middleware\Cache as CacheMiddleware;
use Statamic\StaticCaching\NoCache\Session;
use Tests\TestCase;

class ProjectHeroCacheTest extends TestCase
{
    public function test_warmed_default_file_cannot_be_read_or_overwritten_by_hero_variants(): void
    {
        $directory = sys_get_temp_dir().'/project-hero-cache-'.bin2hex(random_bytes(6));
        config(['statamic.static_caching.replacers' => []]);
        config(['statamic.static_caching.strategies.full.path' => $directory]);
        $cacher = StaticCache::driver('full');
        $url = 'http://localhost/projekte/erstwaehlerforum-stade';
        $default = Request::create($url);
        try {
            $cacher->cachePage($default, response('cached original hero'));
            $this->assertTrue($cacher->hasCachedPage($default));
            foreach (['original'] as $hero) {
                $request = Request::create($url.'?hero='.$hero);
                $middleware = new CacheMiddleware($cacher, new Session($request->fullUrl()));
                $response = $middleware->handle($request, fn () => response('fresh '.$hero.' hero'));
                $this->assertSame('fresh '.$hero.' hero', $response->getContent());
                $this->assertFalse($cacher->hasCachedPage($request));
            }
            $this->assertSame('cached original hero', $cacher->getCachedPage($default)->toResponse($default)->getContent());
            $this->assertSame($cacher->getFilePath($cacher->getUrl($default)), $cacher->getFilePath($cacher->getUrl(Request::create($url.'?utm_source=test'))));
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
