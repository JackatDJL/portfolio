<?php

namespace Tests\Feature;

use App\Support\ContentSyncCandidateValidator;
use App\Support\ContentSyncConflictDocument;
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
        file_put_contents($this->seed.'/.gitattributes', "public/documents/*.bin binary\n");
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
        config()->set('content-sync.recovery_path', $this->fixtureRoot.'/recovery');
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

    public function test_status_reports_an_unsupported_candidate_as_unavailable_not_busy(): void
    {
        file_put_contents($this->production.'/README.md', "local README\n");
        $this->git($this->production, ['add', 'README.md']);
        $this->git($this->production, ['commit', '-m', 'content: local README edit']);
        $this->git($this->production, ['push', 'origin', 'content-sync']);

        $this->git($this->seed, ['switch', 'main']);
        file_put_contents($this->seed.'/README.md', "incoming README\n");
        $this->git($this->seed, ['add', 'README.md']);
        $this->git($this->seed, ['commit', '-m', 'content: incoming README edit']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        try {
            app(PortfolioContentSync::class)->run();
            $this->fail('The merge should preserve the unsupported conflict in its candidate.');
        } catch (ContentSyncFailure $failure) {
            $this->assertContains('README.md', $failure->conflictingPaths);
        }

        $controlDirectory = $this->fixtureRoot.'/state/control';
        mkdir($controlDirectory, 0770, true);
        config()->set('content-sync.control_state_path', $controlDirectory.'/state.json');
        config()->set('content-sync.control_log_path', $controlDirectory.'/sync.log');

        $response = $this->actingAs(User::make()->id('content-sync-status-test')->email('status@example.invalid')->makeSuper())
            ->getJson('/cp/content-sync/status');

        $response->assertOk()
            ->assertJsonPath('conflicts.busy', false)
            ->assertJsonPath('conflicts.unavailable', true)
            ->assertJsonPath('conflicts.files', []);
        $this->assertStringNotContainsString('README.md', $response->getContent());
    }

    public function test_dirty_candidate_without_merge_head_remains_recovery_required(): void
    {
        $this->git($this->production, ['worktree', 'add', '-b', 'portfolio-content-sync-candidate-dirty', $this->candidate, 'HEAD']);
        file_put_contents($this->candidate.'/content/collections/manual-recovery.md', "uncommitted recovery edit\n");
        $sync = app(PortfolioContentSync::class);

        $this->assertFalse($sync->conflictSummary()['merge_in_progress']);
        $this->assertTrue($sync->conflictSummary()['recovery_required']);
        $this->assertTrue($sync->conflictDetails()['recovery_required']);

        $controlDirectory = $this->fixtureRoot.'/state/control';
        mkdir($controlDirectory, 0770, true);
        config()->set('content-sync.control_state_path', $controlDirectory.'/state.json');
        config()->set('content-sync.control_log_path', $controlDirectory.'/sync.log');
        $response = $this->actingAs(User::make()->id('content-sync-dirty-candidate-test')->email('dirty@example.invalid')->makeSuper())
            ->getJson('/cp/content-sync/status');
        $response->assertOk()
            ->assertJsonPath('conflicts.recovery_required', true)
            ->assertJsonPath('conflicts.files', []);
        $this->assertStringNotContainsString('uncommitted recovery edit', $response->getContent());

        try {
            $sync->run();
            $this->fail('The regular writer must reject the same dirty candidate.');
        } catch (ContentSyncFailure) {
            $this->assertFileExists($this->candidate.'/content/collections/manual-recovery.md');
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
        $validator->shouldReceive('validate')->once()->with($this->candidate, ['content/collections/entry.md'], \Mockery::type('string'))
            ->andReturnUsing(function (string $candidate): string {
                $this->assertFileExists($this->lock.'.recovery-required');
                $this->assertStringNotContainsString('<<<<<<<', file_get_contents($this->candidate.'/content/collections/entry.md'));

                return $this->git($candidate, ['write-tree']);
            });
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
        $this->assertFileDoesNotExist($this->lock.'.recovery-required');

        Artisan::shouldReceive('call')->once()->with('statamic:stache:clear', ['--no-interaction' => true])->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('statamic:stache:warm', ['--no-interaction' => true])->andReturn(0);
        $continued = $sync->run();

        $this->assertSame('synced', $continued['status']);
        $this->assertFileDoesNotExist($this->candidate.'/content/collections/entry.md');
        $this->assertStringContainsString('title: Resolved in Control Panel', file_get_contents($this->production.'/content/collections/entry.md'));
        $this->assertStringNotContainsString('<<<<<<<', file_get_contents($this->production.'/content/collections/entry.md'));
    }

    public function test_resolving_to_head_bytes_still_validates_conflicts_and_all_staged_editorial_changes(): void
    {
        $conflictPath = 'content/collections/entry.md';
        $cleanPath = 'content/globals/cleanly-merged.yaml';
        file_put_contents($this->production.'/'.$conflictPath, "---\ntitle: Local edit\n---\nLocal body\n");
        $this->git($this->production, ['add', '--all']);
        $this->git($this->production, ['commit', '-m', 'content: local resolution path fixture']);
        $this->git($this->production, ['push', 'origin', 'content-sync']);
        $this->git($this->seed, ['switch', 'main']);
        file_put_contents($this->seed.'/'.$conflictPath, "---\ntitle: Incoming edit\n---\nIncoming body\n");
        file_put_contents($this->seed.'/'.$cleanPath, "title: Cleanly merged\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: conflict and independent editorial change']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        try {
            app(PortfolioContentSync::class)->run();
            $this->fail('The merge should stop on the changed entry.');
        } catch (ContentSyncFailure $failure) {
            $this->assertContains($conflictPath, $failure->conflictingPaths);
        }

        $sync = app(PortfolioContentSync::class);
        $details = $sync->conflictDetails();
        $validator = \Mockery::mock(ContentSyncCandidateValidator::class);
        $validator->shouldReceive('validate')->once()->with($this->candidate, [$conflictPath, $cleanPath], \Mockery::type('string'))
            ->andReturnUsing(function (string $candidate, array $paths): string {
                $this->assertSame([$conflictPath = 'content/collections/entry.md', $cleanPath = 'content/globals/cleanly-merged.yaml'], $paths);
                $staged = explode("\0", $this->git($candidate, ['diff', '--cached', '--name-only', '-z']));
                $this->assertNotContains($conflictPath, $staged);
                $this->assertContains($cleanPath, $staged);

                return $this->git($candidate, ['write-tree']);
            });
        app()->instance(ContentSyncCandidateValidator::class, $validator);

        $oursBlob = new Process(['git', '-C', $this->candidate, 'show', ':2:'.$conflictPath]);
        $oursBlob->mustRun();
        $localBytes = $oursBlob->getOutput();
        $details['files'][0]['sections'] = array_map(static fn (array $section): array => ['id' => $section['id'], 'choice' => 'ours'], $details['files'][0]['sections']);
        $sync->resolveConflicts($details['fingerprint'], [
            $conflictPath => ['sections' => $details['files'][0]['sections']],
        ]);

        $this->assertSame($localBytes, file_get_contents($this->candidate.'/'.$conflictPath));
        $this->assertFileExists($this->candidate.'/'.$cleanPath);
        $this->assertSame('', $this->git($this->candidate, ['status', '--porcelain']));
        $this->assertFileDoesNotExist($this->lock.'.recovery-required');
    }

    public function test_a_validator_with_an_unconfirmed_tree_hash_cannot_continue_the_merge(): void
    {
        file_put_contents($this->production.'/content/collections/entry.md', "---\ntitle: Production edit\n---\nLocal body\n");
        $this->git($this->seed, ['switch', 'main']);
        file_put_contents($this->seed.'/content/collections/entry.md', "---\ntitle: Main edit\n---\nOriginal body\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: require pinned validator tree']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        try {
            app(PortfolioContentSync::class)->run();
            $this->fail('The merge should stop on a real content conflict.');
        } catch (ContentSyncFailure) {
            // Preserve the isolated conflict for the strict validator result check.
        }

        $path = 'content/collections/entry.md';
        $sync = app(PortfolioContentSync::class);
        $details = $sync->conflictDetails();
        $validator = \Mockery::mock(ContentSyncCandidateValidator::class);
        $validator->shouldReceive('validate')->once()->with($this->candidate, [$path], \Mockery::type('string'))->andReturn(str_repeat('0', 40));
        app()->instance(ContentSyncCandidateValidator::class, $validator);

        try {
            $sync->resolveConflicts($details['fingerprint'], [$path => ['sections' => [['id' => 0, 'choice' => 'ours']]]]);
            $this->fail('A missing validator tree hash must block merge continuation.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('did not confirm the pinned staged tree', $failure->getMessage());
        }

        $this->assertStringContainsString('<<<<<<<', file_get_contents($this->candidate.'/'.$path));
        $this->assertNotSame('', $this->git($this->candidate, ['rev-parse', '--verify', 'MERGE_HEAD']));
        $this->assertFileDoesNotExist($this->lock.'.recovery-required');
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

    public function test_a_concurrent_index_change_during_validation_is_preserved_for_manual_recovery(): void
    {
        file_put_contents($this->production.'/content/collections/entry.md', "---\ntitle: Production edit\n---\nLocal body\n");
        $this->git($this->seed, ['switch', 'main']);
        file_put_contents($this->seed.'/content/collections/entry.md', "---\ntitle: Main edit\n---\nOriginal body\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: conflict before concurrent candidate stage']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        try {
            app(PortfolioContentSync::class)->run();
            $this->fail('The merge should stop on a real content conflict.');
        } catch (ContentSyncFailure) {
            // Preserve the conflict for the concurrent index mutation check.
        }

        $sync = app(PortfolioContentSync::class);
        $details = $sync->conflictDetails();
        $concurrentPath = 'content/globals/concurrent-edit.yaml';
        $concurrentTree = null;
        $validator = \Mockery::mock(ContentSyncCandidateValidator::class);
        $validator->shouldReceive('validate')->once()->andReturnUsing(function () use ($concurrentPath, &$concurrentTree): string {
            file_put_contents($this->candidate.'/'.$concurrentPath, "value: staged while validation was running\n");
            $this->git($this->candidate, ['add', '--', $concurrentPath]);
            $concurrentTree = $this->git($this->candidate, ['write-tree']);
            throw new ContentSyncFailure('The staged candidate changed during validation.');
        });
        app()->instance(ContentSyncCandidateValidator::class, $validator);

        try {
            $sync->resolveConflicts($details['fingerprint'], [
                'content/collections/entry.md' => ['sections' => [['id' => 0, 'choice' => 'ours']]],
            ]);
            $this->fail('A changed index must be retained for manual recovery.');
        } catch (ContentSyncFailure $failure) {
            $this->assertTrue($failure->recoveryRequired);
            $this->assertStringContainsString('manual inspection', strtolower($failure->getMessage()));
        }

        $this->assertNotNull($concurrentTree);
        $this->assertSame($concurrentTree, $this->git($this->candidate, ['write-tree']));
        $this->assertSame("value: staged while validation was running\n", file_get_contents($this->candidate.'/'.$concurrentPath));
        $this->assertFileExists($this->lock.'.recovery-required');
        $this->assertTrue($sync->conflictSummary()['recovery_required']);
    }

    public function test_failed_validation_removes_a_resolution_file_that_was_absent_beforehand(): void
    {
        file_put_contents($this->production.'/content/collections/entry.md', "---\ntitle: Production edit\n---\nLocal body\n");
        $this->git($this->seed, ['switch', 'main']);
        file_put_contents($this->seed.'/content/collections/entry.md', "---\ntitle: Main edit\n---\nOriginal body\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: conflict with missing candidate file']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        try {
            app(PortfolioContentSync::class)->run();
            $this->fail('The merge should stop on a real content conflict.');
        } catch (ContentSyncFailure) {
            // Preserve the unresolved candidate for this rollback check.
        }

        $path = 'content/collections/entry.md';
        unlink($this->candidate.'/'.$path);
        $sync = app(PortfolioContentSync::class);
        $details = $sync->conflictDetails();
        $validator = \Mockery::mock(ContentSyncCandidateValidator::class);
        $validator->shouldReceive('validate')->once()->andThrow(new ContentSyncFailure('Candidate validation failed.'));
        app()->instance(ContentSyncCandidateValidator::class, $validator);

        try {
            $sync->resolveConflicts($details['fingerprint'], [$path => ['choice' => 'ours']]);
            $this->fail('A failed validation must remove the newly written file again.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('Candidate validation failed', $failure->getMessage());
        }

        $this->assertFileDoesNotExist($this->candidate.'/'.$path);
        $this->assertStringNotContainsString('<<<<<<<', file_get_contents($this->production.'/'.$path));
    }

    public function test_conflict_resolution_validates_pre_staged_candidate_files_before_merge_continue(): void
    {
        $path = 'content/globals/resolvable.yaml';
        $preStagedPath = 'content/globals/pre-staged-invalid.yaml';
        file_put_contents($this->production.'/'.$path, "value: local\n");
        file_put_contents($this->production.'/'.$preStagedPath, "value: local\n");
        $this->git($this->production, ['add', '--all']);
        $this->git($this->production, ['commit', '-m', 'content: local staged validation fixture']);
        $this->git($this->production, ['push', 'origin', 'content-sync']);
        $this->git($this->seed, ['switch', 'main']);
        file_put_contents($this->seed.'/'.$path, "value: incoming\n");
        file_put_contents($this->seed.'/'.$preStagedPath, "value: incoming\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: incoming staged validation fixture']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        try {
            app(PortfolioContentSync::class)->run();
            $this->fail('The merge should preserve its content conflict.');
        } catch (ContentSyncFailure) {
            // Keep the isolated candidate for validation.
        }

        file_put_contents($this->candidate.'/'.$preStagedPath, "value: [unterminated\n");
        $this->git($this->candidate, ['add', '--', $preStagedPath]);
        file_put_contents($this->candidate.'/'.$preStagedPath, "value: corrected only in the worktree\n");
        $sync = app(PortfolioContentSync::class);
        $details = $sync->conflictDetails();
        $this->assertSame([$path], array_column($details['files'], 'path'));
        $sections = array_map(static fn (array $section): array => [
            'id' => $section['id'],
            'choice' => 'ours',
        ], $details['files'][0]['sections']);

        try {
            $sync->resolveConflicts($details['fingerprint'], [
                $path => ['sections' => $sections],
            ]);
            $this->fail('A malformed pre-staged YAML file must block merge continuation.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('Candidate validation failed', $failure->getMessage());
        }

        $this->assertSame("value: corrected only in the worktree\n", file_get_contents($this->candidate.'/'.$preStagedPath));
        $this->assertSame('value: [unterminated', $this->git($this->candidate, ['show', ':'.$preStagedPath]));
        $this->assertStringContainsString($preStagedPath, $this->git($this->candidate, ['diff', '--cached', '--name-only']));
        $this->assertStringContainsString($path, $this->git($this->candidate, ['diff', '--name-only', '--diff-filter=U']));
        $this->assertNotSame('', $this->git($this->candidate, ['rev-parse', '--verify', 'MERGE_HEAD']));
        $this->assertFileDoesNotExist($this->lock.'.recovery-required');
    }

    public function test_failed_removal_during_rollback_is_reported_as_incomplete(): void
    {
        file_put_contents($this->production.'/content/collections/entry.md', "---\ntitle: Production edit\n---\nLocal body\n");
        $this->git($this->seed, ['switch', 'main']);
        file_put_contents($this->seed.'/content/collections/entry.md', "---\ntitle: Main edit\n---\nOriginal body\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: conflict with rollback removal failure']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        try {
            app(PortfolioContentSync::class)->run();
            $this->fail('The merge should stop on a real content conflict.');
        } catch (ContentSyncFailure) {
            // Preserve the unresolved candidate for this rollback check.
        }

        $path = 'content/collections/entry.md';
        unlink($this->candidate.'/'.$path);
        $sync = app(PortfolioContentSync::class);
        $details = $sync->conflictDetails();
        $validator = \Mockery::mock(ContentSyncCandidateValidator::class);
        $validator->shouldReceive('validate')->once()->andReturnUsing(function () use ($path): void {
            $fullPath = $this->candidate.'/'.$path;
            unlink($fullPath);
            mkdir($fullPath, 0770, true);
            file_put_contents($fullPath.'/recovery-marker', 'manual inspection required');
            throw new ContentSyncFailure('Candidate validation failed.');
        });
        app()->instance(ContentSyncCandidateValidator::class, $validator);

        try {
            $sync->resolveConflicts($details['fingerprint'], [$path => ['choice' => 'ours']]);
            $this->fail('A failed rollback removal must not be reported as successful.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('rollback could not be completed', strtolower($failure->getMessage()));
        }

        $this->assertDirectoryExists($this->candidate.'/'.$path);
        $this->assertFileExists($this->candidate.'/'.$path.'/recovery-marker');
        $this->assertTrue($sync->conflictSummary()['recovery_required']);
        $this->assertTrue($sync->conflictDetails()['recovery_required']);
        $rootHead = $this->git($this->production, ['rev-parse', 'HEAD']);

        try {
            $sync->run();
            $this->fail('A recovery marker must block regular sync before changes.');
        } catch (ContentSyncFailure $failure) {
            $this->assertTrue($failure->recoveryRequired);
        }

        $this->assertSame($rootHead, $this->git($this->production, ['rev-parse', 'HEAD']));

        try {
            $sync->resolveConflicts('0', []);
            $this->fail('A recovery marker must block further conflict resolution.');
        } catch (ContentSyncFailure $failure) {
            $this->assertTrue($failure->recoveryRequired);
        }

        $controlDirectory = $this->fixtureRoot.'/state/control';
        mkdir($controlDirectory, 0770, true);
        config()->set('content-sync.control_state_path', $controlDirectory.'/state.json');
        config()->set('content-sync.control_log_path', $controlDirectory.'/sync.log');
        $response = $this->actingAs(User::make()->id('content-sync-manual-recovery-test')->email('manual-recovery@example.invalid')->makeSuper())
            ->getJson('/cp/content-sync/status');
        $response->assertOk()
            ->assertJsonPath('conflicts.recovery_required', true)
            ->assertJsonPath('conflicts.files', []);
        $this->assertStringNotContainsString('manual inspection required', $response->getContent());
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
        $validator->shouldReceive('validate')->once()->with($this->candidate, ['content/collections/entry.md'], \Mockery::type('string'))
            ->andReturnUsing(fn (string $candidate): string => $this->git($candidate, ['write-tree']));
        app()->instance(ContentSyncCandidateValidator::class, $validator);
        try {
            $sync->resolveConflicts($details['fingerprint'], [
                'content/collections/entry.md' => ['sections' => [['id' => 0, 'choice' => 'theirs']]],
            ]);
            $this->fail('The pre-commit hook must stop merge continuation.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('could not confirm', strtolower($failure->getMessage()));
        }

        $this->assertSame($originalFile, file_get_contents($this->candidate.'/content/collections/entry.md'));
        $this->assertSame($originalIndex, file_get_contents($indexPath));
        $this->assertNotSame('', $this->git($this->candidate, ['rev-parse', '--verify', 'MERGE_HEAD']));
        $this->assertTrue($sync->conflictDetails()['available']);
    }

    public function test_a_commit_hook_cannot_change_the_validated_tree_without_recovery_blocking(): void
    {
        file_put_contents($this->production.'/content/collections/entry.md', "---\ntitle: Production edit\n---\nLocal body\n");
        $this->git($this->seed, ['switch', 'main']);
        file_put_contents($this->seed.'/content/collections/entry.md', "---\ntitle: Main edit\n---\nOriginal body\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: hook tree verification fixture']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        try {
            app(PortfolioContentSync::class)->run();
            $this->fail('The merge should stop on a real content conflict.');
        } catch (ContentSyncFailure) {
            // Keep the isolated candidate for the hook regression.
        }

        $sync = app(PortfolioContentSync::class);
        $details = $sync->conflictDetails();
        $hooks = $this->fixtureRoot.'/hooks';
        mkdir($hooks, 0770, true);
        file_put_contents($hooks.'/pre-commit', "#!/bin/sh\nprintf 'value: changed after validation\\n' > content/globals/hook-added.yaml\ngit add -- content/globals/hook-added.yaml\nexit 0\n");
        chmod($hooks.'/pre-commit', 0755);
        $this->git($this->candidate, ['config', 'core.hooksPath', $hooks]);
        $validator = \Mockery::mock(ContentSyncCandidateValidator::class);
        $validator->shouldReceive('validate')->once()->with($this->candidate, ['content/collections/entry.md'], \Mockery::type('string'))
            ->andReturnUsing(fn (string $candidate): string => $this->git($candidate, ['write-tree']));
        app()->instance(ContentSyncCandidateValidator::class, $validator);

        try {
            $sync->resolveConflicts($details['fingerprint'], [
                'content/collections/entry.md' => ['sections' => [['id' => 0, 'choice' => 'theirs']]],
            ]);
            $this->fail('A hook-modified tree must not be accepted as the validated candidate.');
        } catch (ContentSyncFailure $failure) {
            $this->assertTrue($failure->recoveryRequired);
            $this->assertStringContainsString('outcome is uncertain', strtolower($failure->getMessage()));
        }

        $this->assertFileExists($this->lock.'.recovery-required');
        $this->assertFileExists($this->candidate.'/content/globals/hook-added.yaml');
        $this->assertStringNotContainsString('<<<<<<<', file_get_contents($this->production.'/content/collections/entry.md'));
        $this->assertTrue($sync->conflictSummary()['recovery_required']);
        try {
            $sync->run();
            $this->fail('A candidate with an uncertain commit tree must block the regular writer.');
        } catch (ContentSyncFailure $failure) {
            $this->assertTrue($failure->recoveryRequired);
        }
    }

    public function test_file_restore_is_attempted_even_when_index_restore_fails(): void
    {
        file_put_contents($this->production.'/content/collections/entry.md', "---\ntitle: Production edit\n---\nLocal body\n");
        $this->git($this->seed, ['switch', 'main']);
        file_put_contents($this->seed.'/content/collections/entry.md', "---\ntitle: Main edit\n---\nOriginal body\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: fail index recovery independently']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        try {
            app(PortfolioContentSync::class)->run();
            $this->fail('The merge should stop on a real content conflict.');
        } catch (ContentSyncFailure) {
            // Preserve the unresolved candidate for the rollback check.
        }

        $sync = app(PortfolioContentSync::class);
        $details = $sync->conflictDetails();
        $originalFile = file_get_contents($this->candidate.'/content/collections/entry.md');
        $indexPath = $this->git($this->candidate, ['rev-parse', '--git-path', 'index']);
        $gitDirectory = dirname($indexPath);
        $gitDirectoryMode = fileperms($gitDirectory) & 0777;
        $hooks = $this->fixtureRoot.'/hooks';
        mkdir($hooks, 0770, true);
        file_put_contents($hooks.'/pre-commit', "#!/bin/sh\nchmod 0500 ".escapeshellarg($gitDirectory)."\nexit 1\n");
        chmod($hooks.'/pre-commit', 0755);
        $this->git($this->candidate, ['config', 'core.hooksPath', $hooks]);

        $recoveryDirectory = (string) config('content-sync.recovery_path');
        $existingBackups = glob($recoveryDirectory.'/*') ?: [];
        $validator = \Mockery::mock(ContentSyncCandidateValidator::class);
        $validator->shouldReceive('validate')->once()->with($this->candidate, ['content/collections/entry.md'], \Mockery::type('string'))
            ->andReturnUsing(fn (string $candidate): string => $this->git($candidate, ['write-tree']));
        app()->instance(ContentSyncCandidateValidator::class, $validator);
        try {
            $sync->resolveConflicts($details['fingerprint'], [
                'content/collections/entry.md' => ['sections' => [['id' => 0, 'choice' => 'theirs']]],
            ]);
            $this->fail('The pre-commit hook must stop merge continuation.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('rollback could not be completed', strtolower($failure->getMessage()));
            $this->assertTrue($failure->recoveryRequired);
        } finally {
            chmod($gitDirectory, $gitDirectoryMode);
        }

        $this->assertSame($originalFile, file_get_contents($this->candidate.'/content/collections/entry.md'));
        $newBackups = array_values(array_diff(glob($recoveryDirectory.'/*') ?: [], $existingBackups));
        $this->assertCount(2, $newBackups, 'Index and file recovery copies must remain available after incomplete index restoration.');
        $controlDirectory = $this->fixtureRoot.'/state/control';
        mkdir($controlDirectory, 0770, true);
        config()->set('content-sync.control_state_path', $controlDirectory.'/state.json');
        config()->set('content-sync.control_log_path', $controlDirectory.'/sync.log');
        $response = $this->actingAs(User::make()->id('content-sync-recovery-test')->email('recovery@example.invalid')->makeSuper())
            ->getJson('/cp/content-sync/status');
        $response->assertOk()
            ->assertJsonPath('conflicts.recovery_required', true)
            ->assertJsonPath('conflicts.files', []);
        foreach ($newBackups as $backup) {
            @unlink($backup);
        }
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
        $validator->shouldReceive('validate')->once()->andReturnUsing(fn (string $candidate): string => $this->git($candidate, ['write-tree']));
        app()->instance(ContentSyncCandidateValidator::class, $validator);

        try {
            app(PortfolioContentSync::class)->resolveConflicts(app(PortfolioContentSync::class)->conflictDetails()['fingerprint'], [
                $conflictPath => ['sections' => [['id' => 0, 'choice' => 'ours']]],
            ]);
            $this->fail('A dirty candidate must not be reported as ready for the writer.');
        } catch (ContentSyncFailure $failure) {
            $this->assertTrue($failure->recoveryRequired);
        }

        $this->assertSame("local content\n", file_get_contents($this->candidate.'/'.$conflictPath));
        $this->assertSame("unrelated candidate edit\n", file_get_contents($this->candidate.'/'.$unrelatedPath));
        $this->assertFileExists($this->lock.'.recovery-required');
        $tracked = new Process(['git', '-C', $this->candidate, 'ls-files', '--error-unmatch', '--', ':(literal)'.$unrelatedPath]);
        $tracked->run();
        $this->assertNotSame(0, $tracked->getExitCode());
    }

    public function test_a_markdown_heading_underline_in_the_ours_stage_uses_whole_file_resolution(): void
    {
        $details = $this->createTextConflict(
            'content/collections/entry.md',
            "Overview\n=======\nLocal body\n",
            "Overview\nIncoming body\n",
        );

        $this->assertTrue($details['files'][0]['binary']);
        $this->assertSame([], $details['files'][0]['sections']);
        $this->assertTrue($details['files'][0]['ours_available']);
        $this->assertTrue($details['files'][0]['theirs_available']);
    }

    public function test_candidate_blueprint_changes_are_rejected_before_conflict_validation(): void
    {
        $details = $this->createTextConflict(
            'content/collections/entry.md',
            "Local version\n",
            "Incoming version\n",
            ['resources/blueprints/collections/pages.yaml' => "title: Candidate blueprint\n"],
        );
        $validator = \Mockery::mock(ContentSyncCandidateValidator::class);
        $validator->shouldNotReceive('validate');
        app()->instance(ContentSyncCandidateValidator::class, $validator);

        try {
            app(PortfolioContentSync::class)->resolveConflicts($details['fingerprint'], [
                'content/collections/entry.md' => ['sections' => [['id' => 0, 'choice' => 'ours']]],
            ]);
            $this->fail('The running application blueprint must not validate a different candidate blueprint.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('blueprint', strtolower($failure->getMessage()));
        }

        $this->assertStringContainsString('<<<<<<<', file_get_contents($this->candidate.'/content/collections/entry.md'));
        $this->assertFileDoesNotExist($this->lock.'.recovery-required');
        $this->assertNotSame('', $this->git($this->candidate, ['rev-parse', '--verify', 'MERGE_HEAD']));
    }

    public function test_candidate_blueprint_changes_can_be_resolved_when_they_match_the_running_application(): void
    {
        $blueprintPath = 'resources/blueprints/collections/pages/pages.yaml';
        $blueprintContents = file_get_contents(base_path($blueprintPath));
        $this->assertIsString($blueprintContents);
        $details = $this->createTextConflict(
            'content/collections/entry.md',
            "Local version\n",
            "Incoming version\n",
            [$blueprintPath => $blueprintContents],
        );

        $validator = \Mockery::mock(ContentSyncCandidateValidator::class);
        $validator->shouldReceive('validate')
            ->once()
            ->withArgs(function (string $candidate, array $paths, string $tree): bool {
                $this->assertSame(['content/collections/entry.md'], $paths);

                return $tree === $this->git($candidate, ['write-tree']);
            })
            ->andReturnUsing(fn (string $candidate, array $paths, string $tree): string => $tree);
        app()->instance(ContentSyncCandidateValidator::class, $validator);

        $result = app(PortfolioContentSync::class)->resolveConflicts($details['fingerprint'], [
            'content/collections/entry.md' => ['sections' => [['id' => 0, 'choice' => 'ours']]],
        ]);

        $this->assertSame('resolved', $result['status']);
        $this->assertSame($blueprintContents, file_get_contents($this->candidate.'/'.$blueprintPath));
        $mergeHead = new Process(['git', '-C', $this->candidate, 'rev-parse', '--quiet', '--verify', 'MERGE_HEAD']);
        $mergeHead->run();
        $this->assertNotSame(0, $mergeHead->getExitCode());
    }

    public function test_failed_tree_creation_rolls_back_the_resolver_owned_index(): void
    {
        $path = 'content/collections/entry.md';
        $details = $this->createTextConflict($path, "Local version\n", "Incoming version\n");
        $gitPath = trim((new Process(['which', 'git']))->mustRun()->getOutput());
        $shimDirectory = $this->fixtureRoot.'/git-shim';
        mkdir($shimDirectory, 0770, true);
        $flag = $this->fixtureRoot.'/write-tree-failed';
        $shim = "#!/bin/sh\nif [ \"\$2\" = ".escapeshellarg($this->candidate).' ] && [ "$3" = write-tree ] && [ ! -e '.escapeshellarg($flag).' ]; then : > '.escapeshellarg($flag)."; exit 1; fi\nexec ".escapeshellarg($gitPath)." \"\$@\"\n";
        file_put_contents($shimDirectory.'/git', $shim);
        chmod($shimDirectory.'/git', 0755);
        $originalPath = $_SERVER['PATH'] ?? (getenv('PATH') ?: '');
        $_SERVER['PATH'] = $shimDirectory.PATH_SEPARATOR.$originalPath;
        putenv('PATH='.$_SERVER['PATH']);
        $validator = \Mockery::mock(ContentSyncCandidateValidator::class);
        $validator->shouldNotReceive('validate');
        app()->instance(ContentSyncCandidateValidator::class, $validator);

        try {
            app(PortfolioContentSync::class)->resolveConflicts($details['fingerprint'], [
                $path => ['sections' => [['id' => 0, 'choice' => 'ours']]],
            ]);
            $this->fail('The injected first write-tree failure must stop resolution.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('write-tree', $failure->getMessage());
        } finally {
            putenv('PATH='.$originalPath);
            $_SERVER['PATH'] = $originalPath;
        }

        $this->assertFileExists($flag);
        $this->assertFileDoesNotExist($this->lock.'.recovery-required');
        $this->assertStringContainsString('<<<<<<<', file_get_contents($this->candidate.'/'.$path));
        $this->assertStringContainsString($path, $this->git($this->candidate, ['diff', '--name-only', '--diff-filter=U']));
    }

    public function test_validation_failure_preserves_an_operator_edit_to_the_resolved_candidate(): void
    {
        $path = 'content/collections/entry.md';
        $details = $this->createTextConflict($path, "Local version\n", "Incoming version\n");
        $operatorEdit = "Operator edit during validation\n";
        $validator = \Mockery::mock(ContentSyncCandidateValidator::class);
        $validator->shouldReceive('validate')->once()->andReturnUsing(function () use ($path, $operatorEdit): string {
            file_put_contents($this->candidate.'/'.$path, $operatorEdit);
            throw new ContentSyncFailure('Validation failed.');
        });
        app()->instance(ContentSyncCandidateValidator::class, $validator);

        try {
            app(PortfolioContentSync::class)->resolveConflicts($details['fingerprint'], [
                $path => ['sections' => [['id' => 0, 'choice' => 'ours']]],
            ]);
            $this->fail('An edit made during validation must be preserved for manual recovery.');
        } catch (ContentSyncFailure $failure) {
            $this->assertTrue($failure->recoveryRequired);
            $this->assertStringContainsString('rollback could not be completed', strtolower($failure->getMessage()));
        }

        $this->assertSame($operatorEdit, file_get_contents($this->candidate.'/'.$path));
        $this->assertFileExists($this->lock.'.recovery-required');
        $this->assertTrue(app(PortfolioContentSync::class)->conflictSummary()['recovery_required']);
        $this->assertNotEmpty(glob((string) config('content-sync.recovery_path').'/*') ?: []);
    }

    public function test_binary_resolution_preserves_zero_byte_blobs_and_selected_file_modes(): void
    {
        $modePath = $this->production.'/public/documents/mode.dat';
        $emptyPath = $this->production.'/public/documents/empty.bin';
        $patternPath = $this->production.'/public/documents/pattern.bin';
        file_put_contents($modePath, "base\0bytes");
        file_put_contents($emptyPath, "base\0bytes");
        file_put_contents($patternPath, 'local bytes without a NUL');
        $this->git($this->production, ['add', '--all']);
        file_put_contents($this->production.'/public/documents/empty.bin', "local\0bytes");

        $this->git($this->seed, ['switch', 'main']);
        $modePath = $this->seed.'/public/documents/mode.dat';
        $emptyPath = $this->seed.'/public/documents/empty.bin';
        $patternPath = $this->seed.'/public/documents/pattern.bin';
        file_put_contents($modePath, "incoming\0bytes");
        file_put_contents($emptyPath, '');
        file_put_contents($patternPath, 'incoming bytes without a NUL');
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
            $this->assertContains('public/documents/pattern.bin', $failure->conflictingPaths);
        }

        $sync = app(PortfolioContentSync::class);
        $details = $sync->conflictDetails();
        $validator = \Mockery::mock(ContentSyncCandidateValidator::class);
        $validator->shouldReceive('validate')->once()->andReturnUsing(fn (string $candidate): string => $this->git($candidate, ['write-tree']));
        app()->instance(ContentSyncCandidateValidator::class, $validator);
        $sync->resolveConflicts($details['fingerprint'], [
            'public/documents/mode.dat' => ['choice' => 'ours'],
            'public/documents/empty.bin' => ['choice' => 'theirs'],
            'public/documents/pattern.bin' => ['choice' => 'ours'],
        ]);

        $resolvedModePath = $this->candidate.'/public/documents/mode.dat';
        $resolvedEmptyPath = $this->candidate.'/public/documents/empty.bin';
        $resolvedPatternPath = $this->candidate.'/public/documents/pattern.bin';
        $this->assertSame("local\0bytes", file_get_contents($resolvedModePath));
        $this->assertSame(0755, fileperms($resolvedModePath) & 0777);
        $this->assertFileExists($resolvedEmptyPath);
        $this->assertSame(0, filesize($resolvedEmptyPath));
        $this->assertSame('local bytes without a NUL', file_get_contents($resolvedPatternPath));
    }

    public function test_configured_conflict_marker_size_is_used_for_inspection_and_resolution(): void
    {
        $path = 'content/collections/marker-size.md';
        $attribute = $this->production.'/.gitattributes';
        file_put_contents($attribute, file_get_contents($attribute)."{$path} conflict-marker-size=8\n");
        file_put_contents($this->production.'/'.$path, "---\ntitle: Local version\n---\nLocal body\n");
        $this->git($this->production, ['add', '--all']);
        $this->git($this->production, ['commit', '-m', 'content: configure conflict marker width']);
        $this->git($this->production, ['push', 'origin', 'content-sync']);

        $this->git($this->seed, ['switch', 'main']);
        file_put_contents($this->seed.'/.gitattributes', file_get_contents($this->seed.'/.gitattributes')."{$path} conflict-marker-size=8\n");
        file_put_contents($this->seed.'/'.$path, "---\ntitle: Incoming version\n---\nIncoming body\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: create a wide-marker conflict']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        try {
            app(PortfolioContentSync::class)->run();
            $this->fail('The merge should stop on a real content conflict.');
        } catch (ContentSyncFailure $failure) {
            $this->assertContains($path, $failure->conflictingPaths);
        }

        $sync = app(PortfolioContentSync::class);
        $details = $sync->conflictDetails();
        $this->assertCount(1, $details['files'][0]['sections']);

        $validator = \Mockery::mock(ContentSyncCandidateValidator::class);
        $validator->shouldReceive('validate')->once()
            ->andReturnUsing(fn (string $candidate): string => $this->git($candidate, ['write-tree']));
        app()->instance(ContentSyncCandidateValidator::class, $validator);
        $sync->resolveConflicts($details['fingerprint'], [
            $path => ['sections' => [['id' => 0, 'choice' => 'theirs']]],
        ]);

        $resolved = file_get_contents($this->candidate.'/'.$path);
        $this->assertSame("---\ntitle: Incoming version\n---\nIncoming body\n", $resolved);
        $this->assertFalse(ContentSyncConflictDocument::containsConflictMarker((string) $resolved));
    }

    public function test_a_mode_only_text_conflict_can_be_resolved_by_file_mode(): void
    {
        $path = 'content/collections/mode-only.md';
        file_put_contents($this->production.'/'.$path, "identical content\n");
        $this->git($this->seed, ['switch', 'main']);
        file_put_contents($this->seed.'/'.$path, "identical content\n");
        chmod($this->seed.'/'.$path, 0755);
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: conflicting file mode']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        try {
            app(PortfolioContentSync::class)->run();
            $this->fail('The merge should stop on a mode-only content conflict.');
        } catch (ContentSyncFailure $failure) {
            $this->assertContains($path, $failure->conflictingPaths);
        }

        $sync = app(PortfolioContentSync::class);
        $details = $sync->conflictDetails();
        $file = $details['files'][0];
        $this->assertFalse($file['binary']);
        $this->assertTrue($file['mode_conflict']);
        $this->assertTrue($file['mode_only_conflict']);
        $this->assertSame([], $file['sections']);
        $this->git($this->candidate, ['config', 'core.fileMode', 'false']);

        $validator = \Mockery::mock(ContentSyncCandidateValidator::class);
        $validator->shouldReceive('validate')->once()->with($this->candidate, [$path], \Mockery::type('string'))
            ->andReturnUsing(fn (string $candidate): string => $this->git($candidate, ['write-tree']));
        app()->instance(ContentSyncCandidateValidator::class, $validator);
        $controlDirectory = $this->fixtureRoot.'/state/control';
        mkdir($controlDirectory, 0770, true);
        config()->set('content-sync.control_state_path', $controlDirectory.'/state.json');
        config()->set('content-sync.control_log_path', $controlDirectory.'/sync.log');
        $otherRun = new Process(['/bin/sleep', '20']);
        $otherRun->start();
        $control = app(ContentSyncControl::class);
        $control->begin($otherRun->getPid());

        try {
            $response = $this->actingAs(User::make()->id('content-sync-mode-test')->email('mode@example.invalid')->makeSuper())
                ->postJson('/cp/content-sync/conflicts/resolve', [
                    'fingerprint' => $details['fingerprint'],
                    'resolutions' => [[
                        'path' => $path,
                        'sections' => [],
                        'mode_choice' => 'theirs',
                    ]],
                ]);
            $response->assertStatus(202)
                ->assertJsonPath('resolved', true)
                ->assertJsonPath('started', false);
        } finally {
            $otherRun->stop(0);
            $control->finish('Testlauf beendet.', $otherRun->getPid());
        }

        $resolvedPath = $this->candidate.'/'.$path;
        $this->assertSame("identical content\n", file_get_contents($resolvedPath));
        $this->assertSame(0755, fileperms($resolvedPath) & 0777);
        $this->assertStringStartsWith('100755 blob ', $this->git($this->candidate, ['ls-tree', 'HEAD', '--', 'content/collections/mode-only.md']));
        $this->assertSame('', $this->git($this->candidate, ['status', '--porcelain']));
    }

    public function test_a_failed_finish_log_keeps_the_resolved_response_and_allows_retry(): void
    {
        $path = 'content/collections/retry-after-log-error.md';
        file_put_contents($this->production.'/'.$path, "local version\n");
        $this->git($this->production, ['add', '--all']);
        $this->git($this->production, ['commit', '-m', 'content: local retry fixture']);
        $this->git($this->production, ['push', 'origin', 'content-sync']);
        $this->git($this->seed, ['switch', 'main']);
        file_put_contents($this->seed.'/'.$path, "incoming version\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: incoming retry fixture']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        try {
            app(PortfolioContentSync::class)->run();
            $this->fail('The merge should stop on a real content conflict.');
        } catch (ContentSyncFailure) {
            // Preserve the unresolved candidate for the request test.
        }

        $sync = app(PortfolioContentSync::class);
        $details = $sync->conflictDetails();
        $controlDirectory = $this->fixtureRoot.'/state/control';
        mkdir($controlDirectory, 0770, true);
        $logPath = $controlDirectory.'/sync.log';
        config()->set('content-sync.control_state_path', $controlDirectory.'/state.json');
        config()->set('content-sync.control_log_path', $logPath);

        $validator = \Mockery::mock(ContentSyncCandidateValidator::class);
        $validator->shouldReceive('validate')->once()->andReturnUsing(function (string $candidate) use ($logPath): string {
            unlink($logPath);
            mkdir($logPath, 0770, true);

            return $this->git($candidate, ['write-tree']);
        });
        app()->instance(ContentSyncCandidateValidator::class, $validator);

        $payload = [
            'fingerprint' => $details['fingerprint'],
            'resolutions' => [[
                'path' => $path,
                'sections' => array_map(static fn (array $section): array => [
                    'id' => $section['id'],
                    'choice' => 'ours',
                ], $details['files'][0]['sections']),
            ]],
        ];
        $response = $this->actingAs(User::make()->id('content-sync-retry-test')->email('retry@example.invalid')->makeSuper())
            ->postJson('/cp/content-sync/conflicts/resolve', $payload);

        $response->assertStatus(202)
            ->assertJsonPath('resolved', true)
            ->assertJsonPath('started', false)
            ->assertJsonPath('start_failed', true);
        $this->getJson('/cp/content-sync/status')
            ->assertOk()
            ->assertJsonPath('running', false)
            ->assertJsonPath('conflicts.recovery_required', false);
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
        $validator->shouldReceive('validate')->once()->with($this->candidate, ['content/collections/entry.md'], \Mockery::type('string'))
            ->andReturnUsing(fn (string $candidate): string => $this->git($candidate, ['write-tree']));
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

    /** @param array<string, string> $incomingFiles
     * @return array<string, mixed>
     */
    private function createTextConflict(string $path, string $local, string $incoming, array $incomingFiles = []): array
    {
        $localPath = $this->production.'/'.$path;
        if (! is_dir(dirname($localPath))) {
            mkdir(dirname($localPath), 0770, true);
        }
        file_put_contents($localPath, $local);
        $this->git($this->production, ['add', '--all']);
        $this->git($this->production, ['commit', '-m', 'content: local conflict fixture']);
        $this->git($this->production, ['push', 'origin', 'content-sync']);

        $this->git($this->seed, ['switch', 'main']);
        $incomingPath = $this->seed.'/'.$path;
        if (! is_dir(dirname($incomingPath))) {
            mkdir(dirname($incomingPath), 0770, true);
        }
        file_put_contents($incomingPath, $incoming);
        foreach ($incomingFiles as $extraPath => $contents) {
            $fullPath = $this->seed.'/'.$extraPath;
            if (! is_dir(dirname($fullPath))) {
                mkdir(dirname($fullPath), 0770, true);
            }
            file_put_contents($fullPath, $contents);
        }
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: incoming conflict fixture']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        try {
            app(PortfolioContentSync::class)->run();
            $this->fail('The fixture must create a content conflict.');
        } catch (ContentSyncFailure $failure) {
            $this->assertContains($path, $failure->conflictingPaths);
        }

        return app(PortfolioContentSync::class)->conflictDetails();
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
