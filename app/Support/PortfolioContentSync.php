<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

final class PortfolioContentSync
{
    /** @var list<string> */
    private const EDITORIAL_PATHS = ['content', 'public/assets', 'public/documents'];

    private string $repository;

    private string $candidatePath;

    private string $candidateBranchPrefix;

    /** @var resource|null */
    private $lockHandle = null;

    /** @var array<string, string> */
    private array $gitEnvironment;

    public function run(bool $dryRun = false): array
    {
        $configuredRepository = (string) config('content-sync.repository');
        $this->repository = realpath($configuredRepository) ?: '';
        if ($this->repository === '' || ! is_dir($this->repository)) {
            throw new ContentSyncFailure('The configured persistent content Git working tree is unavailable.');
        }

        $configuredCandidate = (string) config('content-sync.candidate_path');
        $this->candidatePath = $configuredCandidate;
        $this->candidateBranchPrefix = (string) config('content-sync.candidate_branch_prefix');
        $this->gitEnvironment = $this->makeGitEnvironment();

        $lockPath = (string) config('content-sync.lock_path');
        $lockDirectory = dirname($lockPath);
        if (! is_dir($lockDirectory) && ! mkdir($lockDirectory, 0770, true) && ! is_dir($lockDirectory)) {
            throw new ContentSyncFailure('Could not create the content synchronization lock directory.');
        }

        $this->lockHandle = @fopen($lockPath, 'c');
        if (! is_resource($this->lockHandle)) {
            throw new ContentSyncFailure('Could not open the content synchronization lock.');
        }

        if (! flock($this->lockHandle, LOCK_EX | LOCK_NB)) {
            fclose($this->lockHandle);
            $this->lockHandle = null;

            return ['status' => 'busy'];
        }

        try {
            return $this->synchronize($dryRun);
        } finally {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
        }
    }

    private function synchronize(bool $dryRun): array
    {
        $root = $this->repository;
        $gitRoot = $this->git(['rev-parse', '--show-toplevel'])->getOutput();
        if (realpath(trim($gitRoot)) !== $root) {
            throw new ContentSyncFailure('The configured content path is not the root of its Git working tree.');
        }

        $branch = trim($this->git(['branch', '--show-current'])->getOutput());
        $expectedBranch = (string) config('content-sync.branch');
        if ($branch !== $expectedBranch) {
            throw new ContentSyncFailure("The persistent content working tree must be on {$expectedBranch}; found {$branch}.");
        }

        $this->stopForRootMergeState($root);

        $status = $this->workingTreeStatus($root);
        $outside = array_values(array_filter($status, fn (string $path): bool => ! $this->isEditorialPath($path)));
        if ($outside !== []) {
            throw new ContentSyncFailure(sprintf(
                'The persistent content repository has %d changed path(s) outside content/ and public/assets/. No remote integration was attempted.',
                count($outside),
            ));
        }

        if ($dryRun) {
            return $this->dryRun($status);
        }

        $localChangesCommitted = $this->commitEditorialChanges();
        $remainingStatus = $this->workingTreeStatus($root);
        if ($remainingStatus !== []) {
            throw new ContentSyncFailure('The content Git working tree changed outside the synchronization transaction. The remaining edits were left untouched.');
        }

        $this->refreshStacheIfPending();
        $candidateBranch = $this->assertCandidateCanBeResumed();
        $this->git(['fetch', '--no-tags', (string) config('content-sync.remote'),
            'refs/heads/main:refs/remotes/'.config('content-sync.remote').'/main',
            'refs/heads/'.config('content-sync.branch').':refs/remotes/'.config('content-sync.remote').'/'.config('content-sync.branch'),
        ]);

        $remoteMain = $this->revParse('refs/remotes/'.config('content-sync.remote').'/main');
        $remoteContent = $this->revParse('refs/remotes/'.config('content-sync.remote').'/'.config('content-sync.branch'));
        $rootHead = $this->revParse('HEAD');

        $candidateExists = $candidateBranch !== null;

        $needsIntegration = ! $this->isAncestor($root, $remoteMain, $rootHead)
            || ! $this->isAncestor($root, $remoteContent, $rootHead);

        if (! $candidateExists && $needsIntegration) {
            $candidateBranch = $this->createCandidate($rootHead);
            $candidateExists = true;
        }

        $integratedContent = false;
        if ($candidateExists && $candidateBranch !== null) {
            $candidateHead = $this->revParse('HEAD', $this->candidatePath);
            if (! $this->isAncestor($root, $rootHead, $candidateHead)) {
                $this->mergeTarget($this->candidatePath, $rootHead);
            }
            $this->mergeTarget($this->candidatePath, 'refs/remotes/'.config('content-sync.remote').'/'.config('content-sync.branch'));
            $this->mergeTarget($this->candidatePath, 'refs/remotes/'.config('content-sync.remote').'/main');

            $candidateHead = $this->revParse('HEAD', $this->candidatePath);
            $integratedContent = $this->pathsChanged($root, $rootHead, $candidateHead);
            if ($candidateHead !== $rootHead) {
                if ($integratedContent) {
                    $this->writeStachePendingMarker();
                }
                $this->git(['merge', '--ff-only', $candidateBranch], $root);
                $rootHead = $this->revParse('HEAD');
            }
        }

        $remoteContent = $this->revParse('refs/remotes/'.config('content-sync.remote').'/'.config('content-sync.branch'));
        $localHead = $this->revParse('HEAD');
        $pushed = false;
        if ($localHead !== $remoteContent) {
            if (! $this->isAncestor($root, $remoteContent, $localHead)) {
                throw new ContentSyncFailure('The local content branch no longer contains the fetched content-sync tip; no push was attempted.');
            }
            $this->git(['push', (string) config('content-sync.remote'), 'HEAD:refs/heads/'.config('content-sync.branch')]);
            $pushed = true;
        }

        if ($integratedContent) {
            $this->refreshStacheIfPending();
        }

        if ($candidateExists && $candidateBranch !== null && $this->revParse('HEAD') === $this->revParse('HEAD', $this->candidatePath)) {
            $this->removeCandidate($candidateBranch);
        }

        if (! $localChangesCommitted && ! $pushed && ! $integratedContent) {
            Log::info('Portfolio content synchronization found no changes.');

            return ['status' => 'noop'];
        }

        $result = [
            'status' => 'synced',
            'local_changes_committed' => $localChangesCommitted,
            'remote_content_integrated' => $integratedContent,
            'content_branch_pushed' => $pushed,
        ];
        Log::info('Portfolio content synchronization completed.', $result);

        return $result;
    }

    /** @param list<string> $status */
    private function dryRun(array $status): array
    {
        $remote = (string) config('content-sync.remote');
        $listing = $this->git(['ls-remote', '--heads', $remote, 'refs/heads/main', 'refs/heads/'.config('content-sync.branch')])->getOutput();
        $refs = [];
        foreach (preg_split('/\R/', trim($listing)) ?: [] as $line) {
            if (preg_match('/^([a-f0-9]{40,64})\s+refs\/heads\/(main|content-sync)$/', $line, $matches)) {
                $refs[$matches[2]] = $matches[1];
            }
        }
        if (! isset($refs['main'], $refs['content-sync'])) {
            throw new ContentSyncFailure('Dry run could not find both remote branch tips.');
        }

        $result = [
            'status' => 'dry-run',
            'pending_editorial_paths' => count($status),
            'remote_main' => substr($refs['main'], 0, 12),
            'remote_content_sync' => substr($refs['content-sync'], 0, 12),
        ];
        Log::info('Portfolio content synchronization dry run.', $result);

        return $result;
    }

    private function commitEditorialChanges(): bool
    {
        $this->git(['add', '--all', '--', ...self::EDITORIAL_PATHS]);
        if ($this->gitResult(['diff', '--cached', '--quiet', '--', ...self::EDITORIAL_PATHS])->getExitCode() === 0) {
            return false;
        }

        $this->git([
            '-c', 'user.name='.(string) config('content-sync.author_name'),
            '-c', 'user.email='.(string) config('content-sync.author_email'),
            'commit', '--no-gpg-sign', '-m', 'content: capture Statamic editorial changes',
        ]);
        Log::info('Committed local Statamic editorial changes before remote integration.');

        return true;
    }

    private function createCandidate(string $rootHead): string
    {
        if (file_exists($this->candidatePath)) {
            throw new ContentSyncFailure('The persistent candidate path already exists and will not be overwritten.');
        }
        if (! is_dir(dirname($this->candidatePath)) && ! mkdir(dirname($this->candidatePath), 0770, true) && ! is_dir(dirname($this->candidatePath))) {
            throw new ContentSyncFailure('Could not create the persistent content synchronization candidate directory.');
        }

        $branch = $this->candidateBranchPrefix.date('YmdHis').'-'.bin2hex(random_bytes(4));
        $this->git(['worktree', 'add', '--detach', $this->candidatePath, $rootHead]);
        $this->git(['switch', '-c', $branch], $this->candidatePath);

        return $branch;
    }

    private function assertCandidateCanBeResumed(): ?string
    {
        if (! is_dir($this->candidatePath)) {
            return null;
        }

        $top = $this->gitResult(['rev-parse', '--show-toplevel'], $this->candidatePath);
        if ($top->getExitCode() !== 0 || realpath(trim($top->getOutput())) !== realpath($this->candidatePath)) {
            throw new ContentSyncFailure('The content synchronization candidate path is not a valid Git worktree. Inspect it before removal.');
        }

        $mergeHead = $this->gitResult(['rev-parse', '--quiet', '--verify', 'MERGE_HEAD'], $this->candidatePath);
        if ($mergeHead->getExitCode() === 0) {
            throw $this->conflictFailure($this->candidatePath, 'A previous content synchronization merge is unresolved.');
        }

        if ($this->workingTreeStatus($this->candidatePath) !== []) {
            throw new ContentSyncFailure('The persistent synchronization candidate has uncommitted recovery edits. Finish or inspect that worktree before retrying.');
        }

        $branch = trim($this->git(['branch', '--show-current'], $this->candidatePath)->getOutput());
        if (! str_starts_with($branch, $this->candidateBranchPrefix)) {
            throw new ContentSyncFailure('The persistent synchronization candidate branch is not recognized. Inspect it before reuse.');
        }

        return $branch;
    }

    private function mergeTarget(string $cwd, string $target): void
    {
        $targetSha = $this->revParse($target, $cwd);
        $head = $this->revParse('HEAD', $cwd);
        if ($this->isAncestor($cwd, $targetSha, $head)) {
            return;
        }

        $merge = $this->gitResult([
            '-c', 'user.name='.(string) config('content-sync.author_name'),
            '-c', 'user.email='.(string) config('content-sync.author_email'),
            'merge', '--no-edit', $targetSha,
        ], $cwd);
        if ($merge->getExitCode() === 0) {
            return;
        }

        $conflicts = $this->unmergedPaths($cwd);
        if ($conflicts !== []) {
            throw new ContentSyncFailure(
                sprintf('Git conflict while integrating %s. The candidate merge is preserved for recovery; conflicting paths: %s', substr($targetSha, 0, 12), implode(', ', $conflicts)),
                $conflicts,
            );
        }

        throw new ContentSyncFailure(sprintf('Git could not integrate %s (exit code %d). The candidate worktree is preserved.', substr($targetSha, 0, 12), $merge->getExitCode()));
    }

    private function removeCandidate(string $branch): void
    {
        $this->git(['worktree', 'remove', $this->candidatePath]);
        $this->git(['branch', '-d', $branch]);
    }

    private function conflictFailure(string $cwd, string $message): ContentSyncFailure
    {
        $paths = $this->unmergedPaths($cwd);

        return new ContentSyncFailure(
            $paths === [] ? $message : $message.' Conflicting paths: '.implode(', ', $paths),
            $paths,
        );
    }

    /** @return list<string> */
    private function unmergedPaths(string $cwd): array
    {
        $result = $this->gitResult(['diff', '--name-only', '--diff-filter=U'], $cwd);

        return array_values(array_filter(preg_split('/\R/', trim($result->getOutput())) ?: []));
    }

    private function stopForRootMergeState(string $cwd): void
    {
        $mergeHead = $this->gitResult(['rev-parse', '--quiet', '--verify', 'MERGE_HEAD'], $cwd);
        if ($mergeHead->getExitCode() === 0) {
            throw $this->conflictFailure($cwd, 'The persistent content working tree already has an unfinished Git merge. It was left untouched.');
        }
    }

    /** @return list<string> */
    private function workingTreeStatus(string $cwd): array
    {
        $result = $this->git(['status', '--porcelain=v1', '-z', '--untracked-files=all'], $cwd)->getOutput();
        $entries = array_values(array_filter(explode("\0", $result), static fn (string $entry): bool => $entry !== ''));
        $paths = [];

        for ($index = 0; $index < count($entries); $index++) {
            $entry = $entries[$index];
            if (strlen($entry) < 4 || $entry[2] !== ' ') {
                $paths[] = $entry;

                continue;
            }

            $paths[] = substr($entry, 3);
            if (str_contains($entry[0].$entry[1], 'R') || str_contains($entry[0].$entry[1], 'C')) {
                if (isset($entries[$index + 1])) {
                    $paths[] = $entries[++$index];
                }
            }
        }

        return $paths;
    }

    private function isEditorialPath(string $path): bool
    {
        return $path === 'content' || str_starts_with($path, 'content/')
            || $path === 'public/assets' || str_starts_with($path, 'public/assets/')
            || $path === 'public/documents' || str_starts_with($path, 'public/documents/');
    }

    private function pathsChanged(string $cwd, string $from, string $to): bool
    {
        if ($from === $to) {
            return false;
        }

        return $this->gitResult(['diff', '--quiet', $from, $to, '--', ...self::EDITORIAL_PATHS], $cwd)->getExitCode() !== 0;
    }

    private function isAncestor(string $cwd, string $ancestor, string $descendant): bool
    {
        return $this->gitResult(['merge-base', '--is-ancestor', $ancestor, $descendant], $cwd)->getExitCode() === 0;
    }

    private function revParse(string $ref, ?string $cwd = null): string
    {
        return trim($this->git(['rev-parse', '--verify', $ref.'^{commit}'], $cwd)->getOutput());
    }

    private function refreshStacheIfPending(): void
    {
        $marker = (string) config('content-sync.stache_pending_path');
        if (! is_file($marker)) {
            return;
        }

        $clear = Artisan::call('statamic:stache:clear', ['--no-interaction' => true]);
        if ($clear !== 0) {
            throw new ContentSyncFailure('Statamic Stache clear failed after content integration; the pending marker was retained for retry.');
        }
        $warm = Artisan::call('statamic:stache:warm', ['--no-interaction' => true]);
        if ($warm !== 0) {
            throw new ContentSyncFailure('Statamic Stache warm failed after content integration; the pending marker was retained for retry.');
        }

        @unlink($marker);
        Log::info('Statamic Stache cleared and warmed after remote editorial content changed.');
    }

    private function writeStachePendingMarker(): void
    {
        $marker = (string) config('content-sync.stache_pending_path');
        if (! is_dir(dirname($marker)) && ! mkdir(dirname($marker), 0770, true) && ! is_dir(dirname($marker))) {
            throw new ContentSyncFailure('Could not record that the Statamic Stache needs refreshing.');
        }
        if (file_put_contents($marker, "pending\n", LOCK_EX) === false) {
            throw new ContentSyncFailure('Could not record that the Statamic Stache needs refreshing.');
        }
        @chmod($marker, 0600);
    }

    /** @return array<string, string> */
    private function makeGitEnvironment(): array
    {
        $environment = ['GIT_TERMINAL_PROMPT' => '0'];
        $key = (string) config('content-sync.ssh_key');
        $knownHosts = (string) config('content-sync.known_hosts');
        if ($key !== '' || $knownHosts !== '') {
            if ($key === '' || $knownHosts === '' || ! is_readable($key) || ! is_readable($knownHosts)) {
                throw new ContentSyncFailure('The content Git SSH key and verified known_hosts file must both be mounted and readable.');
            }
            $environment['GIT_SSH_COMMAND'] = 'ssh -i '.escapeshellarg($key)
                .' -o IdentitiesOnly=yes -o BatchMode=yes -o StrictHostKeyChecking=yes'
                .' -o UserKnownHostsFile='.escapeshellarg($knownHosts);
        }

        return $environment;
    }

    private function git(array $arguments, ?string $cwd = null): Process
    {
        $process = $this->gitResult($arguments, $cwd);
        if ($process->getExitCode() !== 0) {
            $operation = implode(' ', array_map(static fn (string $arg): string => preg_match('#^[A-Za-z0-9._:/-]+$#', $arg) ? $arg : '[argument]', $arguments));
            throw new ContentSyncFailure(sprintf('Git operation "%s" failed (exit code %d). No force push or reset was attempted.', $operation, $process->getExitCode()));
        }

        return $process;
    }

    private function gitResult(array $arguments, ?string $cwd = null): Process
    {
        $process = new Process(['git', '-C', $cwd ?? $this->repository, ...$arguments], null, $this->gitEnvironment, null, 300);
        $process->run();

        return $process;
    }
}
