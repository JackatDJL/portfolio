<?php

namespace Tests\Feature;

use App\Support\CvPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\GlobalSet;
use Tests\TestCase;

class CvPdfCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pdf_cache_reuses_identical_content_and_regenerates_after_a_statamic_change(): void
    {
        $variables = GlobalSet::find('site')->inDefaultSite();
        $original = $variables->data()->all();
        [$binary, $counter] = $this->fakeLualatex();
        config()->set('cv.lualatex', $binary);
        $firstEmail = 'cache-first-'.bin2hex(random_bytes(6)).'@example.invalid';
        $nextEmail = 'cache-next-'.bin2hex(random_bytes(6)).'@example.invalid';
        $pdfs = [];
        $expiredLock = null;

        try {
            $variables->set('email', $firstEmail);
            $renderer = app(CvPdfRenderer::class);
            $pdfs[] = $renderer->render();
            $pdfs[] = $renderer->render();

            $this->assertSame($pdfs[0], $pdfs[1]);
            $this->assertCount(1, file($counter, FILE_IGNORE_NEW_LINES));
            $this->assertStringStartsWith(storage_path('app/private/cv-cache/public/'), $pdfs[0]);
            $this->assertSame(0600, fileperms($pdfs[0]) & 0777);
            $this->assertSame(0700, fileperms(dirname($pdfs[0])) & 0777);

            $expired = dirname($pdfs[0]).'/expired-cache-entry.pdf';
            file_put_contents($expired, "%PDF-1.4\n%%EOF\n");
            touch($expired, time() - (32 * 86400));
            $expiredLock = substr($expired, 0, -4).'.lock';
            file_put_contents($expiredLock, '');

            $variables->set('email', $nextEmail);
            $pdfs[] = $renderer->render();

            $this->assertNotSame($pdfs[0], $pdfs[2]);
            $this->assertCount(2, file($counter, FILE_IGNORE_NEW_LINES));
            $this->assertFileDoesNotExist($expired);
            $this->assertSame([], glob(dirname($pdfs[0]).'/*.tmp'));
        } finally {
            $variables->data($original);
            $this->removePublicPdfs($pdfs);
            if ($expiredLock) {
                @unlink($expiredLock);
            }
            @unlink($binary);
            @unlink($counter);
        }
    }

    public function test_profile_and_accent_changes_create_distinct_public_cache_entries(): void
    {
        $site = GlobalSet::find('site')->inDefaultSite();
        $originalSite = $site->data()->all();
        $profile = Entries::query()->where('collection', 'cv_profiles')->where('slug', 'jobmesse-26')->first();
        $this->assertNotNull($profile);
        $originalProfile = $profile->data()->all();
        [$binary, $counter] = $this->fakeLualatex();
        config()->set('cv.lualatex', $binary);
        $site->set('email', 'profile-cache-'.bin2hex(random_bytes(6)).'@example.invalid');
        $pdfs = [];

        try {
            $renderer = app(CvPdfRenderer::class);
            $pdfs[] = $renderer->render();
            $pdfs[] = $renderer->render('jobmesse-26');

            $profile->set('accent_color', '#2356b8');
            $pdfs[] = $renderer->render('jobmesse-26');
            $profile->set('accent_color', '#a34d27');
            $pdfs[] = $renderer->render('jobmesse-26');

            $this->assertCount(4, array_unique($pdfs));
            $this->assertCount(4, file($counter, FILE_IGNORE_NEW_LINES));
        } finally {
            $site->data($originalSite);
            $profile->data($originalProfile);
            $this->removePublicPdfs($pdfs);
            @unlink($binary);
            @unlink($counter);
        }
    }

    public function test_private_pdfs_are_never_reused_or_written_to_the_public_cache(): void
    {
        $variables = GlobalSet::find('cv')->inDefaultSite();
        $original = $variables->data()->all();
        [$binary, $counter] = $this->fakeLualatex();
        config()->set('cv.lualatex', $binary);
        $token = str_repeat('b', 64);
        $privatePdfs = [];

        try {
            $variables->set('private_email', Crypt::encryptString('private-cache-sentinel@example.invalid'));
            $renderer = app(CvPdfRenderer::class);
            $public = $renderer->render();
            $privatePdfs[] = $renderer->render(null, true, $token);
            $privatePdfs[] = $renderer->render(null, true, $token);

            $this->assertSame($public, $renderer->render());
            $this->assertNotSame($privatePdfs[0], $privatePdfs[1]);
            foreach ($privatePdfs as $pdf) {
                $this->assertStringStartsWith(storage_path('app/private/cv-pdf/'), $pdf);
                $this->assertFileExists($pdf);
            }
            $this->assertCount(3, file($counter, FILE_IGNORE_NEW_LINES));
            $publicCache = glob(storage_path('app/private/cv-cache/public/*.pdf')) ?: [];
            $this->assertContains($public, $publicCache);
            foreach ($privatePdfs as $privatePdf) {
                $this->assertNotContains($privatePdf, $publicCache);
            }
        } finally {
            $variables->data($original);
            foreach ($privatePdfs as $pdf) {
                @unlink($pdf);
            }
            $this->removePublicPdfs([$public ?? '']);
            @unlink($binary);
            @unlink($counter);
        }
    }

    public function test_concurrent_identical_public_requests_compile_once(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The concurrency check requires pcntl.');
        }

        $variables = GlobalSet::find('site')->inDefaultSite();
        $original = $variables->data()->all();
        $variables->set('email', 'parallel-cache-'.bin2hex(random_bytes(6)).'@example.invalid');
        [$binary, $counter] = $this->fakeLualatex(1);
        config()->set('cv.lualatex', $binary);
        $resultsDirectory = storage_path('framework/testing/cv-cache-results-'.bin2hex(random_bytes(6)));
        mkdir($resultsDirectory, 0700, true);
        $children = [];
        $pdfs = [];

        try {
            for ($index = 0; $index < 2; $index++) {
                $pid = pcntl_fork();
                if ($pid === -1) {
                    throw new \RuntimeException('Could not fork a CV cache test worker.');
                }
                if ($pid === 0) {
                    try {
                        file_put_contents($resultsDirectory.'/'.$index, app(CvPdfRenderer::class)->render());
                        exit(0);
                    } catch (\Throwable $error) {
                        file_put_contents($resultsDirectory.'/'.$index.'.error', get_class($error));
                        exit(1);
                    }
                }
                $children[] = $pid;
            }

            foreach ($children as $pid) {
                pcntl_waitpid($pid, $status);
                $this->assertTrue(pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0);
            }

            $pdfs = [file_get_contents($resultsDirectory.'/0'), file_get_contents($resultsDirectory.'/1')];
            $this->assertSame($pdfs[0], $pdfs[1]);
            $this->assertCount(1, file($counter, FILE_IGNORE_NEW_LINES));
        } finally {
            $variables->data($original);
            $this->removePublicPdfs($pdfs);
            foreach (glob($resultsDirectory.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($resultsDirectory);
            @unlink($binary);
            @unlink($counter);
        }
    }

    /** @return array{string, string} */
    private function fakeLualatex(int $delaySeconds = 0): array
    {
        $directory = storage_path('framework/testing');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        $binary = $directory.'/lualatex-cache-test-'.bin2hex(random_bytes(8));
        $counter = $binary.'.count';
        $script = "#!/bin/sh\n"
            ."for argument in \"\$@\"; do\n"
            ."  if [ \"\$argument\" = \"--version\" ]; then printf 'LuaLaTeX cache test\\n'; exit 0; fi\n"
            ."done\n"
            ."output=\n"
            ."for argument in \"\$@\"; do case \"\$argument\" in --output-directory=*) output=\$(printf '%s' \"\$argument\" | cut -d= -f2-);; esac; done\n"
            ."printf 'x\\n' >> '$counter'\n"
            .($delaySeconds > 0 ? 'sleep '.$delaySeconds."\n" : '')
            ."printf '\\045PDF-1.4\\n%%%%EOF\\n' > \"\$output/cv.pdf\"\n";

        file_put_contents($binary, $script);
        chmod($binary, 0700);

        return [$binary, $counter];
    }

    private function removePublicPdfs(array $pdfs): void
    {
        foreach (array_unique(array_filter($pdfs)) as $pdf) {
            if (str_starts_with($pdf, storage_path('app/private/cv-cache/public/'))) {
                @unlink($pdf);
                @unlink(substr($pdf, 0, -4).'.lock');
            }
        }
    }
}
