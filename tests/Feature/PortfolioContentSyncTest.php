<?php

namespace Tests\Feature;

use App\Support\ContentSyncCandidateValidator;
use App\Support\ContentSyncControl;
use App\Support\ContentSyncFailure;
use App\Support\PortfolioContentSync;
use Illuminate\Support\Facades\Artisan;
use Statamic\Facades\User;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PortfolioContentSyncTest extends TestCase
{
    private string $fixtureRoot;

    private string $remote;

    private string $seed;

    private string $production;

    private string $lock;

    private string $candidate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureRoot = sys_get_temp_dir().'/portfolio-content-sync-'.bin2hex(random_bytes(8));
        $this->remote = $this->fixtureRoot.'/remote.git';
        $this->seed = $this->fixtureRoot.'/seed';
        $this->production = $this->fixtureRoot.'/production';
        $this->lock = $this->fixtureRoot.'/state/locks/content-sync.lock';
        $this->candidate = $this->fixtureRoot.'/state/content-sync-candidate';

        mkdir($this->fixtureRoot, 0770, true);
        $this->git($this->fixtureRoot, ['init', '--bare', $this->remote]);
        $this->git($this->fixtureRoot, ['init', '-b', 'main', $this->seed]);
        $this->git($this->seed, ['config', 'user.name', 'Test Editor']);
        $this->git($this->seed, ['config', 'user.email', 'test@example.invalid']);

        mkdir($this->seed.'/content/collections', 0770, true);
        mkdir($this->seed.'/content/globals', 0770, true);
        mkdir($this->seed.'/content/assets', 0770, true);
        mkdir($this->seed.'/public/assets/.meta', 0770, true);
        mkdir($this->seed.'/public/documents', 0770, true);
        file_put_contents($this->seed.'/.gitattributes', "public/documents/*.bin -diff\n");
        file_put_contents($this->seed.'/content/collections/entry.md', "---\ntitle: Original\n---\nOriginal body\n");
        file_put_contents($this->seed.'/content/globals/cv.yaml', "private_email: encrypted-original\n");
        file_put_contents($this->seed.'/content/assets/assets.yaml', "title: Assets\ndisk: assets\n");
        file_put_contents($this->seed.'/public/assets/.meta/portrait.jpg.yaml', "alt: Portrait\n");
        file_put_contents($this->seed.'/public/documents/publication.pdf', "%PDF-test\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'initial portfolio']);
        $this->git($this->seed, ['remote', 'add', 'origin', $this->remote]);
        $this->git($this->seed, ['push', 'origin', 'main']);
        $this->git($this->remote, ['symbolic-ref', 'HEAD', 'refs/heads/main'], true);
        $this->git($this->seed, ['switch', '-c', 'content-sync']);
        $this->git($this->seed, ['push', '-u', 'origin', 'content-sync']);
        $this->git($this->fixtureRoot, ['clone', '--branch', 'content-sync', $this->remote, $this->production]);
        config()->set('content-sync.repository', $this->production);
        config()->set('content-sync.branch', 'content-sync');
        config()->set('content-sync.remote', 'origin');
        config()->set('content-sync.lock_path', $this->lock);
        config()->set('content-sync.candidate_path', $this->candidate);
        config()->set('content-sync.ssh_key', null);
        config()->set('content-sync.known_hosts', null);
        config()->set('content-sync.author_name', 'Test Production Sync');
        config()->set('content-sync.author_email', 'production@example.invalid');
        config()->set('content-sync.stache_pending_path', $this->fixtureRoot.'/state/stache-pending');
    }

    protected function tearDown(): void
    {
        if (isset($this->fixtureRoot) && is_dir($this->fixtureRoot)) {
            $this->deleteDirectory($this->fixtureRoot);
        }

        parent::tearDown();
    }

    public function test_an_empty_sync_does_not_create_a_commit_or_candidate(): void
    {
        $head = $this->git($this->production, ['rev-parse', 'HEAD']);

        $result = app(PortfolioContentSync::class)->run();

        $this->assertSame('noop', $result['status']);
        $this->assertSame($head, $this->git($this->production, ['rev-parse', 'HEAD']));
        $this->assertDirectoryDoesNotExist($this->candidate);
    }

    public function test_a_valid_existing_candidate_worktree_is_resumed_and_removed_after_integration(): void
    {
        $this->git($this->seed, ['switch', 'main']);
        file_put_contents($this->seed.'/content/collections/resumed-main.md', "---\ntitle: Main change\n---\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: change while candidate is pending']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        $candidateBranch = 'portfolio-content-sync-candidate-existing';
        $this->git($this->production, ['worktree', 'add', '-b', $candidateBranch, $this->candidate, 'HEAD']);

        Artisan::shouldReceive('call')->once()->with('statamic:stache:clear', ['--no-interaction' => true])->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('statamic:stache:warm', ['--no-interaction' => true])->andReturn(0);

        $result = app(PortfolioContentSync::class)->run();

        $this->assertSame('synced', $result['status']);
        $this->assertTrue($result['remote_content_integrated']);
        $this->assertTrue($result['content_branch_pushed']);
        $this->assertFileExists($this->production.'/content/collections/resumed-main.md');
        $this->assertDirectoryDoesNotExist($this->candidate);
        $this->assertSame(
            $this->git($this->production, ['rev-parse', 'HEAD']),
            $this->git($this->remote, ['rev-parse', 'refs/heads/content-sync'], true),
        );
    }

    public function test_a_failed_git_operation_is_reported_with_sanitized_arguments(): void
    {
        config()->set('content-sync.remote', 'invalid remote name');

        try {
            app(PortfolioContentSync::class)->run();
            $this->fail('The missing remote should make fetch fail.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('Git operation "fetch --no-tags [argument] [argument] [argument]" failed (exit code ', $failure->getMessage());
            $this->assertStringNotContainsString('invalid remote name', $failure->getMessage());
            $this->assertSame([], $failure->conflictingPaths);
        }
    }

    public function test_local_statamic_edits_are_committed_before_main_is_integrated_and_pushed(): void
    {
        file_put_contents($this->production.'/content/collections/cp-edit.md', "---\ntitle: Control Panel edit\n---\n");
        file_put_contents($this->production.'/public/assets/uploaded.jpg', "local uploaded bytes\n");
        file_put_contents($this->production.'/public/documents/new-paper.pdf', "%PDF-local\n");

        $this->git($this->seed, ['switch', 'main']);
        file_put_contents($this->seed.'/content/collections/from-main.md', "---\ntitle: Local main edit\n---\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: local editorial change']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        Artisan::shouldReceive('call')->once()->with('statamic:stache:clear', ['--no-interaction' => true])->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('statamic:stache:warm', ['--no-interaction' => true])->andReturn(0);

        $result = app(PortfolioContentSync::class)->run();

        $this->assertSame('synced', $result['status']);
        $this->assertTrue($result['local_changes_committed']);
        $this->assertTrue($result['remote_content_integrated']);
        $this->assertTrue($result['content_branch_pushed']);
        $this->assertFileExists($this->production.'/content/collections/from-main.md');
        $this->assertFileExists($this->production.'/content/collections/cp-edit.md');
        $this->assertSame("local uploaded bytes\n", file_get_contents($this->production.'/public/assets/uploaded.jpg'));
        $this->assertSame("%PDF-local\n", file_get_contents($this->production.'/public/documents/new-paper.pdf'));

        $firstParent = $this->git($this->production, ['log', '--first-parent', '-2', '--format=%s']);
        $this->assertStringContainsString('Merge', explode("\n", $firstParent)[0]);
        $this->assertSame('content: capture Statamic editorial changes', explode("\n", $firstParent)[1]);
        $this->assertSame(
            $this->git($this->production, ['rev-parse', 'HEAD']),
            $this->git($this->remote, ['rev-parse', 'refs/heads/content-sync'], true),
        );
        $this->assertDirectoryDoesNotExist($this->candidate);
    }

    public function test_a_real_content_conflict_is_left_unresolved_outside_the_live_content_tree(): void
    {
        file_put_contents($this->production.'/content/collections/entry.md', "---\ntitle: Production edit\n---\nLocal body\n");
        $this->git($this->seed, ['switch', 'main']);
        file_put_contents($this->seed.'/content/collections/entry.md', "---\ntitle: Main edit\n---\nOriginal body\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: conflicting main edit']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        try {
            app(PortfolioContentSync::class)->run();
            $this->fail('The merge should stop on a real content conflict.');
        } catch (ContentSyncFailure $failure) {
            $this->assertContains('content/collections/entry.md', $failure->conflictingPaths);
        }

        $liveContent = file_get_contents($this->production.'/content/collections/entry.md');
        $this->assertStringContainsString('title: Production edit', $liveContent);
        $this->assertStringNotContainsString('<<<<<<<', $liveContent);
        $this->assertFileExists($this->candidate.'/content/collections/entry.md');
        $this->assertStringContainsString('<<<<<<<', file_get_contents($this->candidate.'/content/collections/entry.md'));
        $this->assertNotSame(
            $this->git($this->remote, ['rev-parse', 'refs/heads/main'], true),
            $this->git($this->remote, ['rev-parse', 'refs/heads/content-sync'], true),
        );
        $this->assertSame('content: capture Statamic editorial changes', $this->git($this->production, ['log', '-1', '--format=%s']));
    }

    public function test_conflict_details_redact_private_cv_values_and_status_contains_only_metadata(): void
    {
        $secret = 'PRIVATE-CV-EMAIL-SENTINEL@example.invalid';
        $localizedSecret = 'PRIVATE-LOCALIZED-CV-PHONE-SENTINEL';
        file_put_contents($this->production.'/content/globals/cv.yaml', "private_email: {$secret}\n");
        mkdir($this->production.'/content/globals/jacks-portfolio', 0770, true);
        file_put_contents($this->production.'/content/globals/jacks-portfolio/cv.yaml', "phone: >-\n  stable line one\n  stable line two\n  {$localizedSecret}\n  stable line three\n");
        $this->git($this->production, ['add', '--all']);

        $this->git($this->seed, ['switch', 'main']);
        file_put_contents($this->seed.'/content/globals/cv.yaml', "private_email: incoming-private-cv-value\n");
        mkdir($this->seed.'/content/globals/jacks-portfolio', 0770, true);
        file_put_contents($this->seed.'/content/globals/jacks-portfolio/cv.yaml', "phone: >-\n  stable line one\n  stable line two\n  incoming-localized-private-cv-value\n  stable line three\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: conflicting CV settings']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        try {
            app(PortfolioContentSync::class)->run();
            $this->fail('The merge should stop on a private CV settings conflict.');
        } catch (ContentSyncFailure $failure) {
            $this->assertContains('content/globals/cv.yaml', $failure->conflictingPaths);
            $this->assertStringNotContainsString($secret, $failure->getMessage());
        }

        $sync = app(PortfolioContentSync::class);
        $summary = $sync->conflictSummary();
        $details = $sync->conflictDetails();
        $this->assertStringNotContainsString($secret, json_encode($summary, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($localizedSecret, json_encode($summary, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($secret, json_encode($details, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($localizedSecret, json_encode($details, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('incoming-private-cv-value', json_encode($details, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('incoming-localized-private-cv-value', json_encode($details, JSON_THROW_ON_ERROR));
        $detailsByPath = collect($details['files'])->keyBy('path');
        foreach (['content/globals/cv.yaml', 'content/globals/jacks-portfolio/cv.yaml'] as $path) {
            $this->assertTrue($detailsByPath[$path]['sections'][0]['protected']);
            $this->assertStringContainsString('[Geschützte CV-Werte werden nicht angezeigt.]', $detailsByPath[$path]['sections'][0]['ours']);
        }
    }

    public function test_a_conflict_can_be_resolved_sectionally_only_after_candidate_validation(): void
    {
        file_put_contents($this->production.'/content/collections/entry.md', "---\ntitle: Production edit\n---\nLocal body\n");
        $this->git($this->seed, ['switch', 'main']);
        file_put_contents($this->seed.'/content/collections/entry.md', "---\ntitle: Main edit\n---\nOriginal body\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: conflicting main edit']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        try {
            app(PortfolioContentSync::class)->run();
            $this->fail('The merge should stop on a real content conflict.');
        } catch (ContentSyncFailure $failure) {
            $this->assertContains('content/collections/entry.md', $failure->conflictingPaths);
        }

        $sync = app(PortfolioContentSync::class);
        $details = $sync->conflictDetails();
        $validator = \Mockery::mock(ContentSyncCandidateValidator::class);
        $validator->shouldReceive('validate')->once()->with($this->candidate, ['content/collections/entry.md']);
        app()->instance(ContentSyncCandidateValidator::class, $validator);

        $result = $sync->resolveConflicts($details['fingerprint'], [
            'content/collections/entry.md' => [
                'sections' => [['id' => 0, 'choice' => 'manual', 'text' => "title: Resolved in Control Panel\n"]],
            ],
        ]);

        $this->assertSame('resolved', $result['status']);
        $candidate = file_get_contents($this->candidate.'/content/collections/entry.md');
        $this->assertStringContainsString('title: Resolved in Control Panel', $candidate);
        $this->assertStringNotContainsString('<<<<<<<', $candidate);
        $this->assertStringContainsString('title: Production edit', file_get_contents($this->production.'/content/collections/entry.md'));
        $this->assertStringNotContainsString('<<<<<<<', file_get_contents($this->production.'/content/collections/entry.md'));
        $this->assertSame('', $this->git($this->candidate, ['status', '--porcelain']));

        Artisan::shouldReceive('call')->once()->with('statamic:stache:clear', ['--no-interaction' => true])->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('statamic:stache:warm', ['--no-interaction' => true])->andReturn(0);
        $continued = $sync->run();

        $this->assertSame('synced', $continued['status']);
        $this->assertFileDoesNotExist($this->candidate.'/content/collections/entry.md');
        $this->assertStringContainsString('title: Resolved in Control Panel', file_get_contents($this->production.'/content/collections/entry.md'));
        $this->assertStringNotContainsString('<<<<<<<', file_get_contents($this->production.'/content/collections/entry.md'));
    }

    public function test_a_failed_candidate_validation_restores_the_unresolved_candidate_and_live_tree(): void
    {
        file_put_contents($this->production.'/content/collections/entry.md', "---\ntitle: Production edit\n---\nLocal body\n");
        $this->git($this->seed, ['switch', 'main']);
        file_put_contents($this->seed.'/content/collections/entry.md', "---\ntitle: Main edit\n---\nOriginal body\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: conflicting main edit']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        try {
            app(PortfolioContentSync::class)->run();
            $this->fail('The merge should stop on a real content conflict.');
        } catch (ContentSyncFailure) {
            // The unresolved candidate is the fixture for this validation failure check.
        }

        $sync = app(PortfolioContentSync::class);
        $details = $sync->conflictDetails();
        $validator = \Mockery::mock(ContentSyncCandidateValidator::class);
        $validator->shouldReceive('validate')->once()->andThrow(new ContentSyncFailure('Candidate validation failed.'));
        app()->instance(ContentSyncCandidateValidator::class, $validator);

        try {
            $sync->resolveConflicts($details['fingerprint'], [
                'content/collections/entry.md' => [
                    'sections' => [['id' => 0, 'choice' => 'theirs']],
                ],
            ]);
            $this->fail('A failed validation must leave the candidate unresolved.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('Candidate validation failed', $failure->getMessage());
        }

        $candidate = file_get_contents($this->candidate.'/content/collections/entry.md');
        $live = file_get_contents($this->production.'/content/collections/entry.md');
        $this->assertStringContainsString('<<<<<<<', $candidate);
        $this->assertStringNotContainsString('<<<<<<<', $live);
        $this->assertStringContainsString('title: Production edit', $live);
        $this->assertContains('content/collections/entry.md', explode("\n", $this->git($this->candidate, ['diff', '--name-only', '--diff-filter=U'])));
    }

    public function test_a_failed_merge_continue_restores_the_original_candidate_file_and_index(): void
    {
        file_put_contents($this->production.'/content/collections/entry.md', "---\ntitle: Production edit\n---\nLocal body\n");
        $this->git($this->seed, ['switch', 'main']);
        file_put_contents($this->seed.'/content/collections/entry.md', "---\ntitle: Main edit\n---\nOriginal body\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: conflicting merge continuation']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        try {
            app(PortfolioContentSync::class)->run();
            $this->fail('The merge should stop on a real content conflict.');
        } catch (ContentSyncFailure) {
            // Preserve the unmerged candidate for the rollback check.
        }

        $sync = app(PortfolioContentSync::class);
        $details = $sync->conflictDetails();
        $originalFile = file_get_contents($this->candidate.'/content/collections/entry.md');
        $indexPath = $this->git($this->candidate, ['rev-parse', '--git-path', 'index']);
        $originalIndex = file_get_contents($indexPath);
        $hooks = $this->fixtureRoot.'/hooks';
        mkdir($hooks, 0770, true);
        file_put_contents($hooks.'/pre-commit', "#!/bin/sh\nexit 1\n");
        chmod($hooks.'/pre-commit', 0755);
        $this->git($this->candidate, ['config', 'core.hooksPath', $hooks]);

        $validator = \Mockery::mock(ContentSyncCandidateValidator::class);
        $validator->shouldReceive('validate')->once()->with($this->candidate, ['content/collections/entry.md']);
        app()->instance(ContentSyncCandidateValidator::class, $validator);
        try {
            $sync->resolveConflicts($details['fingerprint'], [
                'content/collections/entry.md' => ['sections' => [['id' => 0, 'choice' => 'theirs']]],
            ]);
            $this->fail('The pre-commit hook must stop merge continuation.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('could not continue', strtolower($failure->getMessage()));
        }

        $this->assertSame($originalFile, file_get_contents($this->candidate.'/content/collections/entry.md'));
        $this->assertSame($originalIndex, file_get_contents($indexPath));
        $this->assertNotSame('', $this->git($this->candidate, ['rev-parse', '--verify', 'MERGE_HEAD']));
        $this->assertTrue($sync->conflictDetails()['available']);
    }

    public function test_unicode_and_glob_characters_in_conflict_paths_are_literal(): void
    {
        $conflictPath = 'content/collections/bericht-été[1].md';
        file_put_contents($this->production.'/'.$conflictPath, "local content\n");
        $this->git($this->production, ['add', '--all']);
        $this->git($this->seed, ['switch', 'main']);
        file_put_contents($this->seed.'/'.$conflictPath, "incoming content\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: unicode glob conflict path']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        try {
            app(PortfolioContentSync::class)->run();
            $this->fail('The merge should stop on the add-add conflict.');
        } catch (ContentSyncFailure $failure) {
            $this->assertContains($conflictPath, $failure->conflictingPaths);
        }

        $this->assertSame($conflictPath, app(PortfolioContentSync::class)->conflictDetails()['files'][0]['path']);
        $unrelatedPath = 'content/collections/bericht-été1.md';
        file_put_contents($this->candidate.'/'.$unrelatedPath, "unrelated candidate edit\n");
        $validator = \Mockery::mock(ContentSyncCandidateValidator::class);
        $validator->shouldReceive('validate')->once()->with($this->candidate, [$conflictPath]);
        app()->instance(ContentSyncCandidateValidator::class, $validator);

        app(PortfolioContentSync::class)->resolveConflicts(app(PortfolioContentSync::class)->conflictDetails()['fingerprint'], [
            $conflictPath => ['sections' => [['id' => 0, 'choice' => 'ours']]],
        ]);

        $this->assertSame("local content\n", file_get_contents($this->candidate.'/'.$conflictPath));
        $this->assertSame("unrelated candidate edit\n", file_get_contents($this->candidate.'/'.$unrelatedPath));
        $tracked = new Process(['git', '-C', $this->candidate, 'ls-files', '--error-unmatch', '--', ':(literal)'.$unrelatedPath]);
        $tracked->run();
        $this->assertNotSame(0, $tracked->getExitCode());
    }

    public function test_binary_resolution_preserves_zero_byte_blobs_and_selected_file_modes(): void
    {
        $modePath = $this->production.'/public/documents/mode.dat';
        $emptyPath = $this->production.'/public/documents/empty.bin';
        file_put_contents($modePath, "base\0bytes");
        file_put_contents($emptyPath, "base\0bytes");
        $this->git($this->production, ['add', '--all']);
        file_put_contents($this->production.'/public/documents/empty.bin', "local\0bytes");

        $this->git($this->seed, ['switch', 'main']);
        $modePath = $this->seed.'/public/documents/mode.dat';
        $emptyPath = $this->seed.'/public/documents/empty.bin';
        file_put_contents($modePath, "incoming\0bytes");
        file_put_contents($emptyPath, '');
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: binary conflicts']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        file_put_contents($this->production.'/public/documents/mode.dat', "local\0bytes");
        chmod($this->production.'/public/documents/mode.dat', 0755);

        try {
            $result = app(PortfolioContentSync::class)->run();
            $this->fail('The merge should stop on binary content conflicts.');
        } catch (ContentSyncFailure $failure) {
            $this->assertContains('public/documents/mode.dat', $failure->conflictingPaths);
            $this->assertContains('public/documents/empty.bin', $failure->conflictingPaths);
        }

        $sync = app(PortfolioContentSync::class);
        $details = $sync->conflictDetails();
        $validator = \Mockery::mock(ContentSyncCandidateValidator::class);
        $validator->shouldReceive('validate')->once()->withArgs(function (string $candidate, array $paths): bool {
            sort($paths);

            return $candidate === $this->candidate && $paths === ['public/documents/empty.bin', 'public/documents/mode.dat'];
        });
        app()->instance(ContentSyncCandidateValidator::class, $validator);
        $sync->resolveConflicts($details['fingerprint'], [
            'public/documents/mode.dat' => ['choice' => 'ours'],
            'public/documents/empty.bin' => ['choice' => 'theirs'],
        ]);

        $resolvedModePath = $this->candidate.'/public/documents/mode.dat';
        $resolvedEmptyPath = $this->candidate.'/public/documents/empty.bin';
        $this->assertSame("local\0bytes", file_get_contents($resolvedModePath));
        $this->assertSame(0755, fileperms($resolvedModePath) & 0777);
        $this->assertFileExists($resolvedEmptyPath);
        $this->assertSame(0, filesize($resolvedEmptyPath));
    }

    public function test_a_modify_delete_conflict_can_choose_the_deleted_side(): void
    {
        file_put_contents($this->production.'/content/collections/entry.md', "---\ntitle: Production edit\n---\nLocal body\n");
        $this->git($this->seed, ['switch', 'main']);
        unlink($this->seed.'/content/collections/entry.md');
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: delete conflicting entry']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        try {
            app(PortfolioContentSync::class)->run();
            $this->fail('The merge should stop on a modify-delete conflict.');
        } catch (ContentSyncFailure $failure) {
            $this->assertContains('content/collections/entry.md', $failure->conflictingPaths);
        }

        $sync = app(PortfolioContentSync::class);
        $details = $sync->conflictDetails();
        $this->assertTrue($details['files'][0]['binary']);
        $this->assertTrue($details['files'][0]['theirs_deletes']);
        $this->assertFalse($details['files'][0]['theirs_available']);

        $validator = \Mockery::mock(ContentSyncCandidateValidator::class);
        $validator->shouldReceive('validate')->once()->with($this->candidate, ['content/collections/entry.md']);
        app()->instance(ContentSyncCandidateValidator::class, $validator);
        $sync->resolveConflicts($details['fingerprint'], [
            'content/collections/entry.md' => ['choice' => 'theirs'],
        ]);

        $this->assertFileDoesNotExist($this->candidate.'/content/collections/entry.md');
        $this->assertFileExists($this->production.'/content/collections/entry.md');
        $this->assertStringNotContainsString('<<<<<<<', file_get_contents($this->production.'/content/collections/entry.md'));
    }

    public function test_the_cp_endpoint_preserves_manual_replacements_exactly(): void
    {
        file_put_contents($this->production.'/content/collections/entry.md', "---\ntitle: Local\n---\n");
        file_put_contents($this->production.'/content/collections/empty.md', "local content\n");
        $this->git($this->production, ['add', '--all']);
        $this->git($this->seed, ['switch', 'main']);
        file_put_contents($this->seed.'/content/collections/entry.md', "---\ntitle: Incoming\n---\n");
        file_put_contents($this->seed.'/content/collections/empty.md', "incoming content\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: conflicting manual replacements']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        try {
            app(PortfolioContentSync::class)->run();
            $this->fail('The merge should stop on both real content conflicts.');
        } catch (ContentSyncFailure $failure) {
            $this->assertContains('content/collections/entry.md', $failure->conflictingPaths);
            $this->assertContains('content/collections/empty.md', $failure->conflictingPaths);
        }

        $details = app(PortfolioContentSync::class)->conflictDetails();
        $controlDirectory = $this->fixtureRoot.'/state/control';
        mkdir($controlDirectory, 0770, true);
        config()->set('content-sync.control_state_path', $controlDirectory.'/state.json');
        config()->set('content-sync.control_log_path', $controlDirectory.'/sync.log');
        $otherRun = new Process(['/bin/sleep', '20']);
        $otherRun->start();
        $control = app(ContentSyncControl::class);
        $control->begin($otherRun->getPid());

        try {
            $payload = [
                'fingerprint' => $details['fingerprint'],
                'resolutions' => [
                    ['path' => 'content/collections/entry.md', 'sections' => [
                        ['id' => 0, 'choice' => 'manual', 'text' => "  title:  preserved\n"],
                    ]],
                    ['path' => 'content/collections/empty.md', 'sections' => [
                        ['id' => 0, 'choice' => 'manual', 'text' => ''],
                    ]],
                ],
            ];
            $response = $this->actingAs(User::make()->id('content-sync-manual-test')->email('manual@example.invalid')->makeSuper())
                ->call('POST', '/cp/content-sync/conflicts/resolve', [], [], [], [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_ACCEPT' => 'application/json',
                ], json_encode($payload, JSON_THROW_ON_ERROR));

            $response->assertStatus(202)->assertJsonPath('resolved', true)->assertJsonPath('started', false);
        } finally {
            $otherRun->stop(0);
            $control->finish('Testlauf beendet.', $otherRun->getPid());
        }

        $this->assertStringContainsString("  title:  preserved\n", file_get_contents($this->candidate.'/content/collections/entry.md'));
        $this->assertFileExists($this->candidate.'/content/collections/empty.md');
        $this->assertSame('', file_get_contents($this->candidate.'/content/collections/empty.md'));
        $this->assertStringNotContainsString('<<<<<<<', file_get_contents($this->candidate.'/content/collections/entry.md'));
    }

    public function test_dry_run_reports_local_edits_without_committing_or_fetching(): void
    {
        file_put_contents($this->production.'/content/collections/dry-run.md', "---\ntitle: Pending\n---\n");
        $head = $this->git($this->production, ['rev-parse', 'HEAD']);
        $remoteTrackingTip = $this->git($this->production, ['rev-parse', 'refs/remotes/origin/content-sync']);

        $result = app(PortfolioContentSync::class)->run(dryRun: true);

        $this->assertSame('dry-run', $result['status']);
        $this->assertSame(1, $result['pending_editorial_paths']);
        $this->assertSame($head, $this->git($this->production, ['rev-parse', 'HEAD']));
        $this->assertSame($remoteTrackingTip, $this->git($this->production, ['rev-parse', 'refs/remotes/origin/content-sync']));
        $this->assertFileExists($this->production.'/content/collections/dry-run.md');
    }

    public function test_a_second_process_cannot_enter_the_sync_while_the_lock_is_held(): void
    {
        mkdir(dirname($this->lock), 0770, true);
        $code = '$handle = fopen($argv[1], "c"); flock($handle, LOCK_EX); echo "locked"; flush(); usleep(1500000);';
        $locker = new Process([PHP_BINARY, '-r', $code, $this->lock]);
        $locker->start();
        $deadline = microtime(true) + 3;
        while (! str_contains($locker->getOutput(), 'locked') && microtime(true) < $deadline) {
            usleep(10000);
        }
        $this->assertStringContainsString('locked', $locker->getOutput());

        try {
            $result = app(PortfolioContentSync::class)->run();
            $this->assertSame('busy', $result['status']);
        } finally {
            $locker->wait();
        }
    }

    private function git(string $cwd, array $arguments, bool $bare = false): string
    {
        $command = $bare ? ['git', '--git-dir='.$cwd, ...$arguments] : ['git', '-C', $cwd, ...$arguments];
        $process = new Process($command, null, ['GIT_TERMINAL_PROMPT' => '0'], null, 20);
        $process->mustRun();

        return trim($process->getOutput());
    }

    private function deleteDirectory(string $directory): void
    {
        foreach (new \DirectoryIterator($directory) as $item) {
            if ($item->isDot()) {
                continue;
            }
            if ($item->isDir() && ! $item->isLink()) {
                $this->deleteDirectory($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($directory);
    }
}
