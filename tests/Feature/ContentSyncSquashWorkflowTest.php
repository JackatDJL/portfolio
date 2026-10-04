<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class ContentSyncSquashWorkflowTest extends TestCase
{
    private string $root;

    private string $remote;

    private string $seed;

    private string $runner;

    private string $outputFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/portfolio-content-squash-'.bin2hex(random_bytes(8));
        $this->remote = $this->root.'/remote.git';
        $this->seed = $this->root.'/seed';
        $this->runner = $this->root.'/runner';
        $this->outputFile = $this->root.'/github-output';

        mkdir($this->root, 0770, true);
        $this->git($this->root, ['init', '--bare', $this->remote]);
        $this->git($this->root, ['init', '-b', 'main', $this->seed]);
        $this->git($this->seed, ['config', 'user.name', 'Test Editor']);
        $this->git($this->seed, ['config', 'user.email', 'test@example.invalid']);
        mkdir($this->seed.'/content/collections', 0770, true);
        mkdir($this->seed.'/public/assets/.meta', 0770, true);
        mkdir($this->seed.'/public/documents', 0770, true);
        file_put_contents($this->seed.'/content/collections/entry.md', "---\ntitle: Original\n---\n");
        file_put_contents($this->seed.'/public/assets/.meta/portrait.jpg.yaml', "alt: Portrait\n");
        file_put_contents($this->seed.'/public/documents/publication.pdf', "%PDF-base\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'initial content']);
        $this->git($this->seed, ['remote', 'add', 'origin', $this->remote]);
        $this->git($this->seed, ['push', 'origin', 'main']);
        $this->git($this->remote, ['symbolic-ref', 'HEAD', 'refs/heads/main'], true);
        $this->git($this->seed, ['switch', '-c', 'content-sync']);
        $this->git($this->seed, ['push', '-u', 'origin', 'content-sync']);

        $this->git($this->seed, ['switch', 'main']);
        mkdir($this->seed.'/app', 0770, true);
        file_put_contents($this->seed.'/app/CodeOnly.php', "<?php // main code change\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'app: code-only change']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        $this->git($this->root, ['clone', '--branch', 'main', $this->remote, $this->runner]);
        $this->git($this->runner, ['fetch', '--no-tags', 'origin', 'main', 'content-sync']);
    }

    protected function tearDown(): void
    {
        if (isset($this->root) && is_dir($this->root)) {
            $this->deleteDirectory($this->root);
        }

        parent::tearDown();
    }

    public function test_workflow_is_a_noop_without_editorial_divergence_and_squashes_only_editorial_files_when_divergent(): void
    {
        $mainBefore = $this->git($this->runner, ['rev-parse', 'refs/remotes/origin/main']);
        $result = $this->runScript();

        $this->assertSame(0, $result->getExitCode(), $result->getErrorOutput());
        $this->assertStringContainsString('No editorial content diverges from main', $result->getOutput());
        $this->assertSame($mainBefore, $this->git($this->runner, ['rev-parse', 'HEAD']));
        $this->assertStringContainsString("changed=false\n", file_get_contents($this->outputFile));

        $this->git($this->seed, ['switch', 'content-sync']);
        $this->git($this->seed, ['merge', '--no-edit', 'main']);
        $this->git($this->seed, ['push', 'origin', 'content-sync']);
        file_put_contents($this->seed.'/content/collections/production.md', "---\ntitle: CP edit\n---\n");
        file_put_contents($this->seed.'/public/assets/uploaded.jpg', "asset bytes\n");
        file_put_contents($this->seed.'/public/documents/new-paper.pdf', "%PDF-new\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: production changes']);
        $this->git($this->seed, ['push', 'origin', 'content-sync']);
        $this->git($this->runner, ['fetch', '--no-tags', 'origin', 'main', 'content-sync']);
        file_put_contents($this->outputFile, '');

        $result = $this->runScript();

        $this->assertSame(0, $result->getExitCode(), $result->getErrorOutput());
        $this->assertStringContainsString("changed=true\n", file_get_contents($this->outputFile));
        $this->assertSame('content: squash editorial changes from content-sync', $this->git($this->runner, ['log', '-1', '--format=%s']));
        $parents = preg_split('/\s+/', $this->git($this->runner, ['rev-list', '--parents', '-n', '1', 'HEAD']));
        $this->assertCount(2, $parents, 'The editorial changes should be represented by one squash commit.');
        $changedPaths = explode("\n", $this->git($this->runner, ['diff-tree', '--no-commit-id', '--name-only', '-r', 'HEAD']));
        $this->assertEqualsCanonicalizing([
            'content/collections/production.md',
            'public/assets/uploaded.jpg',
            'public/documents/new-paper.pdf',
        ], $changedPaths);
        $this->assertFileExists($this->runner.'/app/CodeOnly.php');
        $this->assertSame($mainBefore, $this->git($this->remote, ['rev-parse', 'refs/heads/main'], true));
    }

    public function test_workflow_refuses_to_squash_content_sync_that_has_not_integrated_current_main(): void
    {
        $this->git($this->seed, ['switch', 'content-sync']);
        file_put_contents($this->seed.'/content/collections/production.md', "---\ntitle: CP edit\n---\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: production changes']);
        $this->git($this->seed, ['push', 'origin', 'content-sync']);

        $this->git($this->seed, ['switch', 'main']);
        file_put_contents($this->seed.'/content/collections/entry.md', "---\ntitle: Newer main edit\n---\n");
        $this->git($this->seed, ['add', '--all']);
        $this->git($this->seed, ['commit', '-m', 'content: newer main edit']);
        $this->git($this->seed, ['push', 'origin', 'main']);

        $this->git($this->runner, ['fetch', '--no-tags', 'origin', 'main', 'content-sync']);
        $mainBefore = $this->git($this->runner, ['rev-parse', 'refs/remotes/origin/main']);
        $runnerHeadBefore = $this->git($this->runner, ['rev-parse', 'HEAD']);
        file_put_contents($this->outputFile, '');

        $result = $this->runScript();

        $this->assertSame(3, $result->getExitCode());
        $this->assertStringContainsString('refusing to squash a stale editorial tree', $result->getErrorOutput());
        $this->assertSame($runnerHeadBefore, $this->git($this->runner, ['rev-parse', 'HEAD']));
        $this->assertStringContainsString('title: Newer main edit', $this->git($this->runner, ['show', 'refs/remotes/origin/main:content/collections/entry.md']));
        $this->assertSame($mainBefore, $this->git($this->remote, ['rev-parse', 'refs/heads/main'], true));
        $this->assertSame('', file_get_contents($this->outputFile));
    }

    private function runScript(): Process
    {
        $process = new Process(['bash', base_path('scripts/squash-content-sync.sh')], $this->runner, ['GITHUB_OUTPUT' => $this->outputFile], null, 20);
        $process->run();

        return $process;
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
