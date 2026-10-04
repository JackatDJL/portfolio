<?php

namespace Tests\Feature;

use App\Support\ContentSyncFailure;
use App\Support\PortfolioContentSync;
use Illuminate\Support\Facades\Artisan;
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
        mkdir($this->seed.'/content/assets', 0770, true);
        mkdir($this->seed.'/public/assets/.meta', 0770, true);
        mkdir($this->seed.'/public/documents', 0770, true);
        file_put_contents($this->seed.'/content/collections/entry.md', "---\ntitle: Original\n---\nOriginal body\n");
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
