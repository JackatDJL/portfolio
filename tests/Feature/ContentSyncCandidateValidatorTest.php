<?php

namespace Tests\Feature;

use App\Support\ContentSyncCandidateValidator;
use App\Support\ContentSyncFailure;
use Symfony\Component\Process\Process;
use Tests\TestCase;

use function Illuminate\Support\php_binary;

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
            $this->validateCandidate(['public/assets/photos/.meta/missing.jpg.yaml']);
            $this->fail('Malformed nested metadata must fail candidate validation.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('Candidate validation failed', $failure->getMessage());
        }

        $this->assertSame("alt: [unterminated\n", file_get_contents($path));
    }

    public function test_pinned_public_documents_are_scanned_for_unresolved_markers(): void
    {
        mkdir($this->candidate.'/public/documents', 0770, true);
        $path = 'public/documents/notes.txt';
        file_put_contents($this->candidate.'/'.$path, "<<<<<<< unresolved\nprivate note\n");

        try {
            $this->validateCandidate([$path]);
            $this->fail('Staged public documents must be scanned for conflict markers.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('Candidate validation failed', $failure->getMessage());
        }
    }

    public function test_staged_marker_scan_uses_the_configured_marker_width(): void
    {
        mkdir($this->candidate.'/public/documents', 0770, true);
        $path = 'public/documents/review-fixture.txt';
        file_put_contents($this->candidate.'/.gitattributes', "{$path} conflict-marker-size=3\n");
        file_put_contents($this->candidate.'/'.$path, "<<< unresolved\n");

        try {
            $this->validateCandidate([$path]);
            $this->fail('The staged marker scan must use the path-specific marker width.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('Candidate validation failed', $failure->getMessage());
        }
    }

    public function test_validation_uses_an_isolated_copy_and_leaves_the_candidate_unchanged(): void
    {
        $path = $this->candidate.'/public/assets/photos/.meta/missing.jpg.yaml';
        $yaml = "alt: Missing but valid asset\n";
        file_put_contents($path, $yaml);
        $beforeFiles = $this->relativeFiles($this->candidate);
        $beforeHash = hash_file('sha256', $path);

        $this->validateCandidate(['public/assets/photos/.meta/missing.jpg.yaml']);

        $this->assertSame($beforeFiles, $this->relativeFiles($this->candidate));
        $this->assertSame($beforeHash, hash_file('sha256', $path));
        $this->assertSame($yaml, file_get_contents($path));
    }

    public function test_pinned_index_export_rejects_a_symlinked_public_ancestor(): void
    {
        $this->deleteDirectory($this->candidate.'/public/assets');
        rmdir($this->candidate.'/public');
        symlink('../outside-validation-root', $this->candidate.'/public');
        $this->git(['init']);
        $this->git(['add', '--', 'public']);
        $tree = $this->git(['write-tree']);

        try {
            app(ContentSyncCandidateValidator::class)->validate(
                $this->candidate,
                ['public/assets/photo.jpg'],
                $tree,
            );
            $this->fail('A symlinked public ancestor must not make validation read outside the staged tree.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('symbolic link', $failure->getMessage());
        }

        $this->assertTrue(is_link($this->candidate.'/public'));
    }

    public function test_validation_copy_rejects_a_relative_symlink_that_would_point_outside_the_copy(): void
    {
        $snapshotRoot = $this->candidate.'/snapshot';
        $source = $snapshotRoot.'/content';
        $target = $this->candidate.'/validation/content';
        mkdir($source.'/folder', 0770, true);
        file_put_contents($source.'/page.md', "title: Candidate page\n");
        symlink('../../../snapshot/content/page.md', $source.'/folder/page-link.md');

        $copyTree = new \ReflectionMethod(ContentSyncCandidateValidator::class, 'copyTree');

        try {
            $copyTree->invoke(new ContentSyncCandidateValidator, $source, $target, $snapshotRoot);
            $this->fail('A copied link must resolve to the matching file inside the isolated validation tree.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('resolve differently', $failure->getMessage());
        }

        $this->assertFileDoesNotExist($target.'/folder/page-link.md');
        $this->assertSame("title: Candidate page\n", file_get_contents($source.'/page.md'));
    }

    public function test_pinned_index_export_validates_staged_bytes_instead_of_worktree_bytes(): void
    {
        $this->git(['init']);
        $path = 'content/globals/review-fixture.yaml';
        mkdir($this->candidate.'/content/globals', 0770, true);
        file_put_contents($this->candidate.'/'.$path, "title: Staged valid value\n");
        $this->git(['add', '--', $path]);
        $pinnedTree = $this->git(['write-tree']);
        $worktreeBytes = "title: [unstaged malformed value\n";
        file_put_contents($this->candidate.'/'.$path, $worktreeBytes);

        $validatedTree = app(ContentSyncCandidateValidator::class)->validate($this->candidate, [$path], $pinnedTree);

        $this->assertSame($pinnedTree, $validatedTree);
        $this->assertSame($worktreeBytes, file_get_contents($this->candidate.'/'.$path));
        $this->assertSame($pinnedTree, $this->git(['write-tree']));
    }

    public function test_pinned_index_export_does_not_apply_git_smudge_filters(): void
    {
        $this->git(['init']);
        $path = 'content/globals/review-fixture.yaml';
        mkdir($this->candidate.'/content/globals', 0770, true);
        file_put_contents($this->candidate.'/.gitattributes', "{$path} filter=validation-smudge\n");
        file_put_contents($this->candidate.'/'.$path, "title: [unterminated\n");
        $this->git(['config', 'filter.validation-smudge.smudge', "printf 'title: Valid\\n'"]);
        $this->git(['add', '--all']);
        $tree = $this->git(['write-tree']);

        try {
            (new ContentSyncCandidateValidator)->validate($this->candidate, [$path], $tree);
            $this->fail('Raw staged YAML must be validated even when a smudge filter could make it valid.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('Candidate validation failed', $failure->getMessage());
        }
    }

    public function test_conflict_markers_in_a_targeted_entry_body_fail_validation(): void
    {
        mkdir($this->candidate.'/content/collections/pages', 0770, true);
        mkdir($this->candidate.'/content/assets', 0770, true);
        file_put_contents($this->candidate.'/content/assets/assets.yaml', "title: Assets\ndisk: assets\n");
        copy(base_path('content/collections/pages.yaml'), $this->candidate.'/content/collections/pages.yaml');
        $path = 'content/collections/pages/home.md';
        $entry = file_get_contents(base_path($path));
        $this->assertIsString($entry);
        file_put_contents($this->candidate.'/'.$path, $entry);
        $this->validateCandidate([$path]);

        file_put_contents($this->candidate.'/'.$path, str_replace(
            'Your blank canvas is ready. Edit this page, build a collection, or poke around until it feels like yours.',
            "<<<<<<< HEAD\nours\n=======\ntheirs\n>>>>>>> main\n",
            $entry,
        ));
        try {
            $this->validateCandidate([$path]);
            $this->fail('Conflict markers in staged Markdown body text must fail validation.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('Candidate validation failed', $failure->getMessage());
        }
    }

    public function test_deleted_asset_container_can_be_validated_with_another_targeted_editorial_file(): void
    {
        mkdir($this->candidate.'/content/globals', 0770, true);
        file_put_contents($this->candidate.'/content/globals/review-fixture.yaml', "title: Valid targeted global\n");

        $this->validateCandidate([
            'content/assets/retired-container.yaml',
            'content/globals/review-fixture.yaml',
        ]);

        $this->assertFileDoesNotExist($this->candidate.'/content/assets/retired-container.yaml');
    }

    public function test_candidate_validator_requires_the_pinned_tree_hash(): void
    {
        $this->expectException(\ArgumentCountError::class);

        app(ContentSyncCandidateValidator::class)->validate($this->candidate, []);
    }

    public function test_candidate_validation_checks_targeted_entries_outside_the_legacy_collection_allowlist(): void
    {
        mkdir($this->candidate.'/content/collections/pages', 0770, true);
        file_put_contents($this->candidate.'/content/collections/pages.yaml', "title: Pages\nstructure:\n  root: true\nroute: '{parent_uri}/{slug}'\npropagate: true\n");
        file_put_contents($this->candidate.'/content/collections/pages/untitled.markdown', "---\nid: 11111111-1111-4111-8111-111111111111\ntemplate: home\nblueprint: pages\n---\nPage body\n");

        try {
            $this->validateCandidate(['content/collections/pages/untitled.markdown']);
            $this->fail('Candidate validation must apply the Pages blueprint to targeted Markdown entries.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('Candidate validation failed', $failure->getMessage());
        }
    }

    public function test_targeted_external_asset_container_fails_closed_without_writing_private_metadata(): void
    {
        mkdir($this->candidate.'/content/assets', 0770, true);
        file_put_contents($this->candidate.'/content/assets/application_documents.yaml', "title: Private documents\ndisk: application_documents\n");
        $privateRoot = $this->candidate.'/private-documents';
        mkdir($privateRoot, 0770, true);
        file_put_contents($privateRoot.'/application.pdf', "%PDF-1.4\nprivate fixture\n");
        try {
            $this->validateCandidate(['content/assets/application_documents.yaml'], ['application_documents' => $privateRoot]);
            $this->fail('Candidate validation must reject a targeted asset container outside its snapshot.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('Candidate validation failed', $failure->getMessage());
        }

        $this->assertFileDoesNotExist($privateRoot.'/.meta/application.pdf.yaml');
    }

    public function test_targeted_application_entry_fails_closed_before_private_asset_hydration(): void
    {
        mkdir($this->candidate.'/content/assets', 0770, true);
        mkdir($this->candidate.'/content/collections/applications', 0770, true);
        file_put_contents($this->candidate.'/content/assets/application_documents.yaml', "title: Private documents\ndisk: application_documents\n");
        file_put_contents($this->candidate.'/content/collections/applications.yaml', "title: Applications\nrevisions: false\ndated: false\ndefault_status: draft\n");
        $entryPath = 'content/collections/applications/22222222-2222-4222-8222-222222222222.md';
        file_put_contents($this->candidate.'/'.$entryPath, "---\nid: 22222222-2222-4222-8222-222222222222\npublished: false\nblueprint: application\ntitle: Synthetic application\ndocuments:\n  mode: override\n  items:\n    - label: Synthetic attachment\n      category: other\n      file:\n        - application.pdf\n---\n");
        $privateRoot = $this->candidate.'/private-documents';
        mkdir($privateRoot, 0770, true);
        file_put_contents($privateRoot.'/application.pdf', "%PDF-1.4\nprivate fixture\n");
        try {
            $this->validateCandidate([$entryPath], ['application_documents' => $privateRoot]);
            $this->fail('A targeted application entry with a private asset field must fail closed before preprocessing.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('Candidate validation failed', $failure->getMessage());
        }

        $this->assertFileDoesNotExist($privateRoot.'/.meta/application.pdf.yaml');
    }

    public function test_targeted_asset_metadata_generation_stays_inside_the_validation_copy(): void
    {
        mkdir($this->candidate.'/content/assets', 0770, true);
        file_put_contents($this->candidate.'/content/assets/application_documents.yaml', "title: Isolated documents\ndisk: assets\n");
        file_put_contents($this->candidate.'/public/assets/photos/new.pdf', "%PDF-1.4\nlocal candidate asset\n");
        $liveRoot = $this->candidate.'/live-assets';
        mkdir($liveRoot.'/photos', 0770, true);
        file_put_contents($liveRoot.'/photos/new.pdf', "%PDF-1.4\nfixture disk sentinel\n");

        $this->validateCandidate(['public/assets/photos/new.pdf'], ['assets' => $liveRoot]);

        $this->assertFileDoesNotExist($this->candidate.'/public/assets/photos/.meta/new.pdf.yaml');
        $this->assertFileDoesNotExist($liveRoot.'/photos/.meta/new.pdf.yaml');
    }

    public function test_targeted_entry_dropped_as_a_duplicate_identifier_fails_validation(): void
    {
        mkdir($this->candidate.'/content/collections/pages', 0770, true);
        file_put_contents($this->candidate.'/content/collections/pages.yaml', "title: Pages\nstructure:\n  root: true\nroute: '{parent_uri}/{slug}'\npropagate: true\n");
        $id = '11111111-1111-4111-8111-111111111111';
        file_put_contents($this->candidate.'/content/collections/pages/a-first.md', "---\nid: {$id}\ntemplate: home\nblueprint: pages\ntitle: First page\n---\nPage body\n");
        file_put_contents($this->candidate.'/content/collections/pages/z-target.md', "---\nid: {$id}\ntemplate: home\nblueprint: pages\ntitle: Target page\n---\nPage body\n");

        try {
            $this->validateCandidate(['content/collections/pages/z-target.md']);
            $this->fail('A targeted entry dropped by the Stache duplicate ID filter must fail validation.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('Candidate validation failed', $failure->getMessage());
        }

        try {
            $this->validateCandidate(['content/collections/pages/a-first.md']);
            $this->fail('The retained duplicate must also fail validation when it displaces an untouched entry.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('Candidate validation failed', $failure->getMessage());
        }
    }

    public function test_malformed_target_entry_front_matter_fails_closed_when_stache_throws(): void
    {
        mkdir($this->candidate.'/content/collections/pages', 0770, true);
        file_put_contents($this->candidate.'/content/collections/pages.yaml', "title: Pages\nstructure:\n  root: true\nroute: '{parent_uri}/{slug}'\npropagate: true\n");
        $path = 'content/collections/pages/malformed.md';
        file_put_contents($this->candidate.'/'.$path, "---\nid: 33333333-3333-4333-8333-333333333333\ntitle: [unterminated\n---\nBody\n");

        try {
            $this->validateCandidate([$path]);
            $this->fail('A validator exception while loading a targeted entry must fail closed.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('Candidate validation failed', $failure->getMessage());
        }
    }

    public function test_uncaught_candidate_parser_errors_do_not_log_private_values(): void
    {
        mkdir($this->candidate.'/content/collections/pages', 0770, true);
        file_put_contents($this->candidate.'/content/collections/pages.yaml', "title: Pages\nstructure:\n  root: true\nroute: '{parent_uri}/{slug}'\npropagate: true\n");
        $privateSentinel = 'PRIVATE-CANDIDATE-LOG-SENTINEL-42';
        $path = 'content/collections/pages/malformed.md';
        file_put_contents($this->candidate.'/'.$path, "---\nid: 33333333-3333-4333-8333-333333333333\ntitle: [{$privateSentinel}\n---\nBody\n");
        $resultPath = $this->candidate.'/validation-result.json';
        $process = new Process(
            [php_binary(), base_path('scripts/validate-content.php')],
            base_path(),
            [
                'APP_ENV' => 'testing',
                'APP_KEY' => (string) config('app.key'),
                'LOG_CHANNEL' => 'stderr',
                'ASSETS_DISK_ROOT' => $this->candidate.'/public/assets',
                'APPLICATION_DOCUMENTS_ROOT' => $this->candidate.'/.external-assets-disabled/application_documents',
                'LOCAL_DISK_ROOT' => $this->candidate.'/.external-assets-disabled/local',
                'CONTENT_SYNC_VALIDATION_ROOT' => $this->candidate,
                'CONTENT_SYNC_VALIDATION_RESULT_PATH' => $resultPath,
                'CONTENT_SYNC_VALIDATION_PATHS' => json_encode([$path], JSON_THROW_ON_ERROR),
            ],
            null,
            300,
        );
        $process->run();

        $output = $process->getOutput().$process->getErrorOutput();
        $this->assertSame(1, $process->getExitCode(), $output);
        $this->assertStringContainsString('Content validation did not complete.', $output);
        $this->assertStringNotContainsString($privateSentinel, $output);
        $this->assertSame(['completed' => false, 'passed' => false], json_decode((string) file_get_contents($resultPath), true));
    }

    public function test_duplicate_entry_identifiers_across_collections_fail_validation(): void
    {
        mkdir($this->candidate.'/content/collections/pages', 0770, true);
        mkdir($this->candidate.'/content/collections/posts', 0770, true);
        file_put_contents($this->candidate.'/content/collections/pages.yaml', "title: Pages\nstructure:\n  root: true\nroute: '{parent_uri}/{slug}'\npropagate: true\n");
        file_put_contents($this->candidate.'/content/collections/posts.yaml', "title: Posts\nrevisions: false\ndated: false\n");
        $id = '44444444-4444-4444-8444-444444444444';
        $target = 'content/collections/pages/target.md';
        file_put_contents($this->candidate.'/'.$target, "---\nid: {$id}\ntemplate: home\nblueprint: pages\ntitle: Target page\n---\nPage body\n");
        file_put_contents($this->candidate.'/content/collections/posts/untouched.markdown', "---\nid: {$id}\ntitle: Untouched post\n---\nPost body\n");

        try {
            $this->validateCandidate([$target]);
            $this->fail('Duplicate identifiers across collections must fail candidate validation.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('Candidate validation failed', $failure->getMessage());
        }
    }

    public function test_nested_group_imports_on_external_asset_disks_fail_before_preprocessing(): void
    {
        $fieldsetsDirectory = resource_path('fieldsets');
        $blueprintsDirectory = resource_path('blueprints/collections/pages');
        $fieldsetPath = $fieldsetsDirectory.'/content-sync-review.yaml';
        $blueprintPath = $blueprintsDirectory.'/content-sync-review.yaml';
        $this->assertFileDoesNotExist($fieldsetPath);
        $this->assertFileDoesNotExist($blueprintPath);
        file_put_contents($fieldsetPath, "title: Review private asset field\nfields:\n  - handle: attachment\n    field:\n      type: assets\n      container: application_documents\n      max_files: 1\n");
        file_put_contents($blueprintPath, "title: Review fixture\nsections:\n  - fields:\n      - handle: wrapper\n        field:\n          type: group\n          fields:\n            - import: content-sync-review\n");

        try {
            mkdir($this->candidate.'/content/collections/pages', 0770, true);
            mkdir($this->candidate.'/content/assets', 0770, true);
            file_put_contents($this->candidate.'/content/collections/pages.yaml', "title: Pages\nstructure:\n  root: true\nroute: '{parent_uri}/{slug}'\npropagate: true\n");
            file_put_contents($this->candidate.'/content/assets/application_documents.yaml', "title: Private documents\ndisk: application_documents\n");
            $path = 'content/collections/pages/nested-import.md';
            file_put_contents($this->candidate.'/'.$path, "---\nid: 55555555-5555-4555-8555-555555555555\ntemplate: home\nblueprint: content-sync-review\ntitle: Nested fieldset fixture\nwrapper:\n  attachment: application.pdf\n---\nPage body\n");
            $privateRoot = $this->candidate.'/private-documents';
            mkdir($privateRoot, 0770, true);
            file_put_contents($privateRoot.'/application.pdf', "%PDF-1.4\nprivate fixture\n");
            config()->set('filesystems.disks.application_documents.root', $privateRoot);

            try {
                $this->validateCandidate([$path]);
                $this->fail('A nested imported asset field on an external disk must fail closed.');
            } catch (ContentSyncFailure $failure) {
                $this->assertStringContainsString('Candidate validation failed', $failure->getMessage());
            }

            $this->assertFileDoesNotExist($privateRoot.'/.meta/application.pdf.yaml');
        } finally {
            @unlink($fieldsetPath);
            @unlink($blueprintPath);
        }
    }

    public function test_targeted_global_fails_closed_before_preprocessing_external_asset_fields(): void
    {
        mkdir($this->candidate.'/content/globals/jacks-portfolio', 0770, true);
        mkdir($this->candidate.'/content/assets', 0770, true);
        file_put_contents($this->candidate.'/content/globals/application_defaults.yaml', "title: Application defaults\n");
        file_put_contents($this->candidate.'/content/globals/jacks-portfolio/application_defaults.yaml', "documents:\n  mode: override\n  items:\n    - label: Synthetic attachment\n      category: other\n      file:\n        - application_documents::synthetic.pdf\n");
        file_put_contents($this->candidate.'/content/assets/application_documents.yaml', "title: Private documents\ndisk: application_documents\n");
        $privateRoot = $this->candidate.'/private-documents';
        mkdir($privateRoot, 0770, true);
        file_put_contents($privateRoot.'/synthetic.pdf', "%PDF-1.4\nsynthetic fixture\n");
        config()->set('filesystems.disks.application_documents.root', $privateRoot);

        try {
            $this->validateCandidate(['content/globals/jacks-portfolio/application_defaults.yaml']);
            $this->fail('A targeted global with an external asset field must fail before preprocessing.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('Candidate validation failed', $failure->getMessage());
        }

        $this->assertFileDoesNotExist($privateRoot.'/.meta/synthetic.pdf.yaml');
    }

    public function test_targeted_asset_metadata_fails_closed_before_external_asset_fields_are_preprocessed(): void
    {
        $blueprintPath = resource_path('blueprints/assets/content-sync-review.yaml');
        $this->assertFileDoesNotExist($blueprintPath);
        file_put_contents($blueprintPath, "title: Review fixture\ntabs:\n  main:\n    sections:\n      - fields:\n          - handle: attachment\n            field:\n              type: assets\n              container: application_documents\n");

        try {
            mkdir($this->candidate.'/content/assets', 0770, true);
            file_put_contents($this->candidate.'/content/assets/content-sync-review.yaml', "title: Public review assets\ndisk: assets\n");
            file_put_contents($this->candidate.'/content/assets/application_documents.yaml', "title: Private documents\ndisk: application_documents\n");
            file_put_contents($this->candidate.'/public/assets/photos/synthetic.jpg', "synthetic asset\n");
            file_put_contents($this->candidate.'/public/assets/photos/.meta/synthetic.jpg.yaml', "attachment: application_documents::synthetic.pdf\n");

            try {
                $this->validateCandidate(['public/assets/photos/.meta/synthetic.jpg.yaml'], ['application_documents' => $this->candidate.'/private-documents']);
                $this->fail('A targeted asset whose blueprint uses an external disk must fail closed.');
            } catch (ContentSyncFailure $failure) {
                $this->assertStringContainsString('Candidate validation failed', $failure->getMessage());
            }
        } finally {
            @unlink($blueprintPath);
        }
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
                $relative = substr($file->getPathname(), strlen($directory) + 1);
                if ($relative !== '.git' && ! str_starts_with($relative, '.git'.DIRECTORY_SEPARATOR)) {
                    $paths[] = $relative;
                }
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

    /** @param list<string> $paths */
    private function validateCandidate(array $paths, array $testDiskRoots = []): string
    {
        if (! is_dir($this->candidate.'/.git')) {
            $this->git(['init']);
        }
        $this->git(['add', '--all']);
        $tree = $this->git(['write-tree']);

        $validator = $testDiskRoots === []
            ? app(ContentSyncCandidateValidator::class)
            : new ContentSyncCandidateValidator($testDiskRoots);

        return $validator->validate($this->candidate, $paths, $tree);
    }

    /** @param list<string> $arguments */
    private function git(array $arguments): string
    {
        $process = new Process(['git', '-C', $this->candidate, ...$arguments], null, ['GIT_TERMINAL_PROMPT' => '0'], null, 20);
        $process->mustRun();

        return trim($process->getOutput());
    }
}
