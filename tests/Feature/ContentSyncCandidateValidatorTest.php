<?php

namespace Tests\Feature;

use App\Support\ContentSyncCandidateValidator;
use App\Support\ContentSyncFailure;
use Tests\TestCase;

class ContentSyncCandidateValidatorTest extends TestCase
{
    private string $candidate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->candidate = sys_get_temp_dir().'/content-sync-validation-'.bin2hex(random_bytes(8));
        mkdir($this->candidate.'/content', 0770, true);
        mkdir($this->candidate.'/public/assets/photos/.meta', 0770, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->candidate)) {
            $this->deleteDirectory($this->candidate);
        }

        parent::tearDown();
    }

    public function test_it_parses_nested_asset_metadata_even_when_the_asset_is_absent(): void
    {
        $path = $this->candidate.'/public/assets/photos/.meta/missing.jpg.yaml';
        file_put_contents($path, "alt: [unterminated\n");

        try {
            app(ContentSyncCandidateValidator::class)->validate($this->candidate, ['public/assets/photos/.meta/missing.jpg.yaml']);
            $this->fail('Malformed nested metadata must fail candidate validation.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('Candidate validation failed', $failure->getMessage());
        }

        $this->assertSame("alt: [unterminated\n", file_get_contents($path));
    }

    public function test_validation_uses_an_isolated_copy_and_leaves_the_candidate_unchanged(): void
    {
        $path = $this->candidate.'/public/assets/photos/.meta/missing.jpg.yaml';
        $yaml = "alt: Missing but valid asset\n";
        file_put_contents($path, $yaml);
        $beforeFiles = $this->relativeFiles($this->candidate);
        $beforeHash = hash_file('sha256', $path);

        app(ContentSyncCandidateValidator::class)->validate($this->candidate, ['public/assets/photos/.meta/missing.jpg.yaml']);

        $this->assertSame($beforeFiles, $this->relativeFiles($this->candidate));
        $this->assertSame($beforeHash, hash_file('sha256', $path));
        $this->assertSame($yaml, file_get_contents($path));
    }

    /** @return list<string> */
    private function relativeFiles(string $directory): array
    {
        $paths = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $paths[] = substr($file->getPathname(), strlen($directory) + 1);
            }
        }
        sort($paths);

        return $paths;
    }

    private function deleteDirectory(string $directory): void
    {
        foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $item) {
            if ($item->isDir() && ! $item->isLink()) {
                $this->deleteDirectory($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($directory);
    }
}
