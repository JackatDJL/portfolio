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
        $this->initialize();
        $this->lockHandle = $this->openSyncLock();

        if (! flock($this->lockHandle, LOCK_EX | LOCK_NB)) {
            fclose($this->lockHandle);
            $this->lockHandle = null;

            return ['status' => 'busy'];
        }

        try {
            if ($this->recoveryRequired()) {
                throw new ContentSyncFailure('The isolated content candidate requires manual recovery. No content was changed.', recoveryRequired: true);
            }

            return $this->synchronize($dryRun);
        } finally {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
        }
    }

    /** @return array{busy: bool, merge_in_progress: bool, recovery_required: bool, files: list<array{path: string, sections: int, binary: bool}>} */
    public function conflictSummary(): array
    {
        $this->initialize(localGitOnly: true);
        $lock = $this->openSyncLock();
        if (! flock($lock, LOCK_SH | LOCK_NB)) {
            fclose($lock);

            return ['busy' => true, 'merge_in_progress' => false, 'recovery_required' => false, 'files' => []];
        }

        try {
            if ($this->recoveryRequired()) {
                return ['busy' => false, 'merge_in_progress' => true, 'recovery_required' => true, 'files' => []];
            }

            $state = $this->inspectCandidate(includeSections: false);

            return [
                'busy' => false,
                'merge_in_progress' => $state['merge_in_progress'],
                'recovery_required' => $state['recovery_required'],
                'files' => array_map(static fn (array $file): array => [
                    'path' => $file['path'],
                    'sections' => count($file['document']['sections']),
                    'binary' => $file['document']['binary'],
                ], $state['files']),
            ];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array{available: bool, recovery_required: bool, fingerprint: string|null, files: list<array<string, mixed>>} */
    public function conflictDetails(): array
    {
        $this->initialize(localGitOnly: true);
        $lock = $this->openSyncLock();
        if (! flock($lock, LOCK_SH | LOCK_NB)) {
            fclose($lock);

            return ['available' => false, 'recovery_required' => false, 'fingerprint' => null, 'files' => []];
        }

        try {
            if ($this->recoveryRequired()) {
                return ['available' => false, 'recovery_required' => true, 'fingerprint' => null, 'files' => []];
            }

            $state = $this->inspectCandidate(includeSections: true);

            return [
                'available' => $state['merge_in_progress'] && $state['files'] !== [],
                'recovery_required' => $state['recovery_required'],
                'fingerprint' => $state['fingerprint'],
                'files' => array_map(function (array $file): array {
                    $document = $file['document'];
                    $sections = array_map(static function (array $section): array {
                        return [
                            'id' => $section['id'],
                            'base' => $section['base'] === null ? null : ContentSyncConflictDocument::displaySection($section['base'], $section['protected']),
                            'ours' => ContentSyncConflictDocument::displaySection($section['ours'], $section['protected']),
                            'theirs' => ContentSyncConflictDocument::displaySection($section['theirs'], $section['protected']),
                            'protected' => $section['protected'],
                        ];
                    }, $document['sections']);

                    return [
                        'path' => $file['path'],
                        'binary' => $document['binary'],
                        'ours_available' => $file['ours_available'],
                        'theirs_available' => $file['theirs_available'],
                        'ours_deletes' => $file['ours_deletes'],
                        'theirs_deletes' => $file['theirs_deletes'],
                        'mode_conflict' => $file['mode_conflict'],
                        'mode_only_conflict' => $file['mode_only_conflict'],
                        'ours_executable' => $file['ours_mode'] === '100755',
                        'theirs_executable' => $file['theirs_mode'] === '100755',
                        'sections' => $sections,
                    ];
                }, $state['files']),
            ];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @param array<string, array<string, mixed>> $resolutions */
    public function resolveConflicts(string $fingerprint, array $resolutions): array
    {
        $this->initialize(localGitOnly: true);
        $lock = $this->openSyncLock();
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            return ['status' => 'busy'];
        }

        try {
            if ($this->recoveryRequired()) {
                throw new ContentSyncFailure('The isolated content candidate requires manual recovery. No content was changed.', recoveryRequired: true);
            }

            $state = $this->inspectCandidate(includeSections: true);
            if (! $state['merge_in_progress'] || $state['files'] === [] || ! is_string($state['fingerprint'])
                || ! hash_equals($state['fingerprint'], $fingerprint)) {
                throw new ContentSyncFailure('The candidate conflict changed or is no longer available. Reload the conflict details.');
            }

            $paths = array_column($state['files'], 'path');
            if (array_diff($paths, array_keys($resolutions)) !== [] || array_diff(array_keys($resolutions), $paths) !== []) {
                throw new ContentSyncFailure('The submitted files do not match the candidate conflict. Reload the conflict details.');
            }

            $resolved = [];
            foreach ($state['files'] as $file) {
                $path = $file['path'];
                $resolution = $resolutions[$path] ?? null;
                if (! is_array($resolution)) {
                    throw new ContentSyncFailure('Choose a resolution for each conflicting file. The candidate was left untouched.');
                }

                if ($file['document']['binary']) {
                    $choice = $resolution['choice'] ?? null;
                    if (! in_array($choice, ['ours', 'theirs'], true)) {
                        throw new ContentSyncFailure('Choose one version for each binary conflict file. The candidate was left untouched.');
                    }
                    $stage = $choice === 'ours' ? 2 : 3;
                    if (! $file[$choice.'_available'] && $file[$choice.'_deletes']) {
                        $resolved[$path] = ['contents' => null, 'mode' => null];

                        continue;
                    }
                    if (! $file[$choice.'_available']) {
                        throw new ContentSyncFailure('The selected version is too large or uses an unsupported file type. The candidate was left untouched.');
                    }
                    $stageInfo = $file['stages'][$stage] ?? null;
                    if (! is_array($stageInfo)) {
                        throw new ContentSyncFailure('The selected version is unavailable for a binary conflict file. The candidate was left untouched.');
                    }
                    $blob = $this->gitResult(['cat-file', 'blob', $stageInfo['blob']], $this->candidatePath);
                    if ($blob->getExitCode() !== 0) {
                        throw new ContentSyncFailure('The selected version is unavailable for a binary conflict file. The candidate was left untouched.');
                    }
                    $resolved[$path] = ['contents' => $blob->getOutput(), 'mode' => $stageInfo['mode']];
                } else {
                    $sections = $resolution['sections'] ?? null;
                    if (! is_array($sections) || ! array_is_list($sections)) {
                        throw new ContentSyncFailure('Choose a resolution for every conflict section. The candidate was left untouched.');
                    }
                    $modeChoice = $resolution['mode_choice'] ?? null;
                    if ($file['mode_conflict'] && ! in_array($modeChoice, ['ours', 'theirs'], true)) {
                        throw new ContentSyncFailure('Choose the file mode for this conflicting text file. The candidate was left untouched.');
                    }
                    $modeStage = $modeChoice === 'theirs' ? 3 : 2;
                    if ($file['mode_only_conflict']) {
                        $stageInfo = $file['stages'][$modeStage] ?? null;
                        if (! is_array($stageInfo)) {
                            throw new ContentSyncFailure('The selected file mode is unavailable. The candidate was left untouched.');
                        }
                        $blob = $this->gitResult(['cat-file', 'blob', $stageInfo['blob']], $this->candidatePath);
                        if ($blob->getExitCode() !== 0) {
                            throw new ContentSyncFailure('The selected file mode is unavailable. The candidate was left untouched.');
                        }
                        $contents = $blob->getOutput();
                    } else {
                        $contents = ContentSyncConflictDocument::compose(
                            $file['contents'],
                            $file['document']['sections'],
                            $sections,
                            $file['marker_size'],
                        );
                    }
                    $resolved[$path] = [
                        'contents' => $contents,
                        'mode' => $file['stages'][$modeStage]['mode'],
                    ];
                }

            }

            $originals = [];
            $indexBackup = null;
            $stagedIndexHash = null;
            $preserveBackups = false;
            $mergeCompleted = false;
            $mergeAttempted = false;
            $outcomeUncertain = false;
            $expectedHead = $state['head'];
            $expectedMergeHeads = $state['merge_heads'];
            $this->writeRecoveryMarker();
            try {
                $indexBackup = $this->backupCandidateIndex();
                foreach ($resolved as $path => $resolution) {
                    $originals[$path] = $this->backupCandidateFile($path);
                    $this->writeCandidateFile($path, $resolution['contents'], $resolution['mode']);
                }
                $pathspecs = array_map(fn (string $path): string => $this->literalPathspec($path), $paths);
                $this->git(['add', '--', ...$pathspecs], $this->candidatePath);
                foreach ($resolved as $path => $resolution) {
                    if ($resolution['contents'] === null) {
                        continue;
                    }
                    $chmod = $resolution['mode'] === '100755' ? '+x' : '-x';
                    $this->git(['update-index', '--chmod='.$chmod, '--', $path], $this->candidatePath);
                    if ($this->candidateIndexMode($path) !== $resolution['mode']) {
                        throw new ContentSyncFailure('The selected candidate file mode could not be staged safely. No live content changed.');
                    }
                }
                $stagedIndexHash = is_array($indexBackup) ? hash_file('sha256', $indexBackup['index_path']) : false;
                if (! is_string($stagedIndexHash)) {
                    throw new ContentSyncFailure('The staged candidate index could not be preserved. No live content changed.');
                }
                if ($this->unmergedPaths($this->candidatePath) !== []) {
                    throw new ContentSyncFailure('Git still reports unresolved candidate files after validation. No live content changed.');
                }
                $validatedTree = $this->candidateIndexTree($this->candidatePath);
                $stagedPaths = $this->changedPathsBetweenTreeAndHead($expectedHead, $validatedTree, $this->candidatePath);
                $editorialStagedPaths = [];
                foreach ($stagedPaths as $stagedPath) {
                    if (str_starts_with($stagedPath, 'resources/blueprints/')
                        || str_starts_with($stagedPath, 'resources/fieldsets/')) {
                        throw new ContentSyncFailure('The candidate includes blueprint or fieldset changes. Update the application definitions before resolving content conflicts.');
                    }
                    if (! $this->isEditorialPath($stagedPath)) {
                        continue;
                    }
                    if (! $this->isSafeConflictPath($stagedPath)) {
                        throw new ContentSyncFailure('The staged candidate contains an unsafe editorial path. No live content changed.');
                    }
                    $editorialStagedPaths[] = $stagedPath;
                }
                $validationPaths = array_values(array_unique([...$paths, ...$editorialStagedPaths]));
                $validatorTree = app(ContentSyncCandidateValidator::class)->validate($this->candidatePath, $validationPaths, $validatedTree);
                if (! is_string($validatorTree) || ! hash_equals($validatedTree, $validatorTree)) {
                    throw new ContentSyncFailure('The candidate validator did not confirm the pinned staged tree. The unresolved candidate was restored and no live content changed.');
                }

                if ($this->revParse('HEAD', $this->candidatePath) !== $expectedHead
                    || $this->candidateMergeHeads($this->candidatePath) !== $expectedMergeHeads
                    || $this->candidateIndexTree($this->candidatePath) !== $validatedTree) {
                    $outcomeUncertain = true;
                    throw new ContentSyncFailure('The isolated candidate changed after validation. Manual inspection is required before retrying.', recoveryRequired: true);
                }

                $mergeAttempted = true;
                $mergeFailure = null;
                try {
                    $this->gitResult([
                        '-c', 'user.name='.(string) config('content-sync.author_name'),
                        '-c', 'user.email='.(string) config('content-sync.author_email'),
                        '-c', 'core.editor=true',
                        'merge', '--continue',
                    ], $this->candidatePath);
                } catch (\Throwable $failure) {
                    $mergeFailure = $failure;
                }
                if ($this->candidateCommitMatches($validatedTree, [$expectedHead, ...$expectedMergeHeads])) {
                    $mergeCompleted = true;
                    $this->clearRecoveryMarker();
                } else {
                    if ($mergeFailure instanceof \Throwable) {
                        throw $mergeFailure;
                    }
                    throw new ContentSyncFailure('Git could not confirm the exact validated candidate tree and parents.');
                }
            } catch (\Throwable $failure) {
                if ($mergeCompleted) {
                    $preserveBackups = true;
                    throw new ContentSyncFailure('The candidate merge completed, but its recovery state could not be cleared. Manual inspection is required before retrying.', recoveryRequired: true);
                }

                if ($mergeAttempted) {
                    try {
                        $confirmedTree = $this->candidateCommitMatches($validatedTree ?? '', [$expectedHead, ...$expectedMergeHeads]);
                    } catch (\Throwable) {
                        $confirmedTree = false;
                    }
                    if ($confirmedTree) {
                        try {
                            $mergeCompleted = true;
                            $this->clearRecoveryMarker();
                        } catch (\Throwable) {
                            $preserveBackups = true;
                            throw new ContentSyncFailure('The candidate merge completed, but its recovery state could not be cleared. Manual inspection is required before retrying.', recoveryRequired: true);
                        }

                        return ['status' => 'resolved'];
                    }

                    try {
                        if ($this->revParse('HEAD', $this->candidatePath) !== $expectedHead
                            || $this->candidateMergeHeads($this->candidatePath) !== $expectedMergeHeads) {
                            $outcomeUncertain = true;
                        }
                    } catch (\Throwable) {
                        $outcomeUncertain = true;
                    }
                }

                if (! $mergeAttempted && is_array($indexBackup)) {
                    try {
                        $headMatches = $this->revParse('HEAD', $this->candidatePath) === $expectedHead;
                        $mergeHeadsMatch = $this->candidateMergeHeads($this->candidatePath) === $expectedMergeHeads;
                        if (isset($validatedTree)) {
                            $indexMatches = hash_equals($validatedTree, $this->candidateIndexTree($this->candidatePath));
                        } else {
                            $currentIndexHash = hash_file('sha256', $indexBackup['index_path']);
                            $expectedIndexHash = $stagedIndexHash;
                            if (! is_string($expectedIndexHash)) {
                                $expectedIndexHash = hash_file('sha256', $indexBackup['backup']);
                            }
                            $indexMatches = is_string($expectedIndexHash) && is_string($currentIndexHash)
                                && hash_equals($expectedIndexHash, $currentIndexHash);
                        }
                        if (! $headMatches || ! $mergeHeadsMatch || ! $indexMatches) {
                            $outcomeUncertain = true;
                        }
                    } catch (\Throwable) {
                        $outcomeUncertain = true;
                    }
                }

                if ($outcomeUncertain || ($failure instanceof ContentSyncFailure && $failure->recoveryRequired)) {
                    $preserveBackups = true;
                    throw new ContentSyncFailure('The candidate merge outcome is uncertain. Its private recovery files and inspection marker were retained; manual inspection is required before retrying.', recoveryRequired: true);
                }

                $rollbackFailed = false;
                if (is_array($indexBackup)) {
                    try {
                        $this->restoreCandidateIndex($indexBackup);
                    } catch (\Throwable) {
                        $rollbackFailed = true;
                    }
                }
                foreach ($originals as $path => $backup) {
                    try {
                        if (! $this->candidateFileMatchesResolution($path, $resolved[$path])) {
                            $rollbackFailed = true;

                            continue;
                        }
                        $this->restoreCandidateFile($path, $backup);
                    } catch (\Throwable) {
                        $rollbackFailed = true;
                    }
                }
                if ($rollbackFailed) {
                    $preserveBackups = true;
                    throw new ContentSyncFailure('The candidate rollback could not be completed. Any private recovery backups were retained; inspect the isolated candidate before retrying. Live content was not changed.', recoveryRequired: true);
                }

                try {
                    $this->clearRecoveryMarker();
                } catch (\Throwable) {
                    $preserveBackups = true;
                    throw new ContentSyncFailure('The candidate was restored, but its recovery state could not be cleared. Manual inspection is required before retrying.', recoveryRequired: true);
                }

                if ($failure instanceof ContentSyncFailure) {
                    throw $failure;
                }
                throw new ContentSyncFailure('Candidate validation failed. The unresolved candidate was restored and no live content changed.');
            } finally {
                if (is_array($indexBackup) && is_string($indexBackup['backup']) && ! $preserveBackups) {
                    @unlink($indexBackup['backup']);
                }
                foreach ($originals as $backup) {
                    if (is_string($backup['backup']) && ! $preserveBackups) {
                        @unlink($backup['backup']);
                    }
                }
            }

            return ['status' => 'resolved'];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function initialize(bool $localGitOnly = false): void
    {
        $configuredRepository = (string) config('content-sync.repository');
        $this->repository = realpath($configuredRepository) ?: '';
        if ($this->repository === '' || ! is_dir($this->repository)) {
            throw new ContentSyncFailure('The configured persistent content Git working tree is unavailable.');
        }

        $this->candidatePath = rtrim((string) config('content-sync.candidate_path'), DIRECTORY_SEPARATOR);
        $this->candidateBranchPrefix = (string) config('content-sync.candidate_branch_prefix');
        $this->gitEnvironment = $localGitOnly ? ['GIT_TERMINAL_PROMPT' => '0'] : $this->makeGitEnvironment();
    }

    /** @return resource */
    private function openSyncLock()
    {
        $lockPath = (string) config('content-sync.lock_path');
        $lockDirectory = dirname($lockPath);
        if (! is_dir($lockDirectory) && ! mkdir($lockDirectory, 0770, true) && ! is_dir($lockDirectory)) {
            throw new ContentSyncFailure('Could not create the content synchronization lock directory.');
        }

        $lock = @fopen($lockPath, 'c');
        if (! is_resource($lock)) {
            throw new ContentSyncFailure('Could not open the content synchronization lock.');
        }

        return $lock;
    }

    private function recoveryRequired(): bool
    {
        $path = (string) config('content-sync.lock_path').'.recovery-required';

        return is_file($path) || is_link($path);
    }

    private function writeRecoveryMarker(): void
    {
        $path = (string) config('content-sync.lock_path').'.recovery-required';
        if (is_file($path) && ! is_link($path)) {
            return;
        }
        if (file_exists($path) || is_link($path)) {
            throw new ContentSyncFailure('The candidate recovery state could not be recorded safely. Manual inspection is required.', recoveryRequired: true);
        }

        $temporary = $path.'.'.bin2hex(random_bytes(6)).'.tmp';
        if (@file_put_contents($temporary, "manual inspection required\n", LOCK_EX) === false
            || ! @chmod($temporary, 0600)
            || ! @rename($temporary, $path)) {
            @unlink($temporary);
            throw new ContentSyncFailure('The candidate recovery state could not be recorded. Manual inspection is required.', recoveryRequired: true);
        }
    }

    private function clearRecoveryMarker(): void
    {
        $path = (string) config('content-sync.lock_path').'.recovery-required';
        if (! file_exists($path) && ! is_link($path)) {
            return;
        }
        if (is_link($path) || ! is_file($path) || ! @unlink($path) || file_exists($path) || is_link($path)) {
            throw new ContentSyncFailure('The candidate recovery state could not be cleared safely.', recoveryRequired: true);
        }
    }

    /** @return array{merge_in_progress: bool, recovery_required: bool, head: string|null, merge_heads: list<string>, fingerprint: string|null, files: list<array{path: string, contents: string|null, document: array{binary: bool, sections: list<array{id: int, base: string|null, ours: string, theirs: string, protected: bool}>}, stages: array<int, array{mode: string, blob: string}>, ours_available: bool, theirs_available: bool, ours_deletes: bool, theirs_deletes: bool, mode_conflict: bool, mode_only_conflict: bool, marker_size: int, ours_mode: string|null, theirs_mode: string|null}>} */
    private function inspectCandidate(bool $includeSections): array
    {
        if (! is_dir($this->candidatePath)) {
            return ['merge_in_progress' => false, 'recovery_required' => false, 'head' => null, 'merge_heads' => [], 'fingerprint' => null, 'files' => []];
        }

        $candidateRoot = realpath($this->candidatePath);
        $top = $this->gitResult(['rev-parse', '--show-toplevel'], $this->candidatePath);
        if ($candidateRoot === false || $top->getExitCode() !== 0 || realpath(trim($top->getOutput())) !== $candidateRoot) {
            throw new ContentSyncFailure('The persistent content candidate is not a valid Git worktree. It was left untouched.');
        }

        $branch = trim($this->git(['branch', '--show-current'], $this->candidatePath)->getOutput());
        if (! str_starts_with($branch, $this->candidateBranchPrefix)) {
            throw new ContentSyncFailure('The persistent content candidate branch is not recognized. It was left untouched.');
        }

        $mergeHead = $this->gitResult(['rev-parse', '--quiet', '--verify', 'MERGE_HEAD'], $this->candidatePath);
        if ($mergeHead->getExitCode() !== 0) {
            $dirtyCandidate = $this->workingTreeStatus($this->candidatePath) !== [];

            return ['merge_in_progress' => false, 'recovery_required' => $dirtyCandidate, 'head' => null, 'merge_heads' => [], 'fingerprint' => null, 'files' => []];
        }

        $head = $this->revParse('HEAD', $this->candidatePath);
        $mergeHeads = $this->candidateMergeHeads($this->candidatePath);
        $paths = $this->unmergedPaths($this->candidatePath);
        if ($paths === []) {
            return ['merge_in_progress' => true, 'recovery_required' => true, 'head' => $head, 'merge_heads' => $mergeHeads, 'fingerprint' => null, 'files' => []];
        }
        $files = [];
        $fingerprintParts = [$head, ...$mergeHeads];
        foreach ($paths as $path) {
            if (! $this->isSafeConflictPath($path)) {
                throw new ContentSyncFailure('The candidate contains a conflict outside the supported editorial paths. It was left untouched.');
            }

            $fullPath = $this->candidateFilePath($path);
            $contents = null;
            if (is_file($fullPath) && ! is_link($fullPath)) {
                $size = filesize($fullPath);
                if (is_int($size) && $size <= 262_144) {
                    $read = file_get_contents($fullPath);
                    $contents = is_string($read) ? $read : null;
                }
            }

            $stages = $this->unmergedStages($path);
            $oursExists = isset($stages[2]);
            $theirsExists = isset($stages[3]);
            $modeConflict = $oursExists && $theirsExists && $stages[2]['mode'] !== $stages[3]['mode'];
            $binaryAttribute = $this->hasBinaryDiffAttribute($path);
            $binary = $contents === null || ! $oursExists || ! $theirsExists
                || str_contains($contents, "\0") || $binaryAttribute;
            $modeOnlyConflict = $modeConflict && ! $binary && $stages[2]['blob'] === $stages[3]['blob'];
            $markerSize = ! $binary && ! $modeOnlyConflict ? $this->conflictMarkerSize($path) : 7;
            if (! $binary && ! $modeOnlyConflict
                && ($this->stageContainsAmbiguousSeparator($stages[2], $markerSize)
                    || $this->stageContainsAmbiguousSeparator($stages[1] ?? null, $markerSize))) {
                // The CP cannot know which equal-width line Git used as its
                // separator. Offer whole-side choices instead of truncated sections.
                $binary = true;
            }
            $document = $binary
                ? ['binary' => true, 'sections' => []]
                : ($modeOnlyConflict ? ['binary' => false, 'sections' => []] : ContentSyncConflictDocument::inspect($contents, $markerSize));
            if ($this->isPrivateCvGlobalPath($path)) {
                foreach ($document['sections'] as &$section) {
                    $section['protected'] = true;
                }
                unset($section);
            }
            $oursAvailable = $oursExists && in_array($stages[2]['mode'], ['100644', '100755'], true) && $this->stageBlobAvailable($stages[2], 52_428_800);
            $theirsAvailable = $theirsExists && in_array($stages[3]['mode'], ['100644', '100755'], true) && $this->stageBlobAvailable($stages[3], 52_428_800);
            $files[] = [
                'path' => $path,
                'contents' => $contents,
                'document' => $document,
                'stages' => $stages,
                'ours_available' => $oursAvailable,
                'theirs_available' => $theirsAvailable,
                'ours_deletes' => ! $oursExists,
                'theirs_deletes' => ! $theirsExists,
                'mode_conflict' => $modeConflict,
                'mode_only_conflict' => $modeOnlyConflict,
                'marker_size' => $markerSize,
                'ours_mode' => $stages[2]['mode'] ?? null,
                'theirs_mode' => $stages[3]['mode'] ?? null,
            ];

            if ($includeSections) {
                $stageStatus = $this->gitResult(['ls-files', '-u', '-z', '--', $this->literalPathspec($path)], $this->candidatePath)->getOutput();
                $fingerprintParts[] = $path."\0".hash('sha256', (string) $contents)."\0".hash('sha256', $stageStatus)."\0".$markerSize;
            }
        }

        $fingerprint = $includeSections
            ? hash_hmac('sha256', implode("\0", $fingerprintParts), (string) config('app.key'))
            : null;

        return ['merge_in_progress' => true, 'recovery_required' => false, 'head' => $head, 'merge_heads' => $mergeHeads, 'fingerprint' => $fingerprint, 'files' => $files];
    }

    /** @param array{mode: string, blob: string} $stage */
    private function stageBlobAvailable(array $stage, int $maxBytes): bool
    {
        $size = $this->gitResult(['cat-file', '-s', $stage['blob']], $this->candidatePath);

        return $size->getExitCode() === 0 && ctype_digit(trim($size->getOutput())) && (int) trim($size->getOutput()) <= $maxBytes;
    }

    /** @param array{mode: string, blob: string}|null $stage */
    private function stageContainsAmbiguousSeparator(?array $stage, int $markerSize): bool
    {
        if ($stage === null) {
            return false;
        }
        if (! $this->stageBlobAvailable($stage, 262_144)) {
            return true;
        }
        $blob = $this->gitResult(['cat-file', 'blob', $stage['blob']], $this->candidatePath);
        if ($blob->getExitCode() !== 0 || str_contains($blob->getOutput(), "\0") || ! mb_check_encoding($blob->getOutput(), 'UTF-8')) {
            return true;
        }

        return ContentSyncConflictDocument::containsSeparatorLine($blob->getOutput(), $markerSize);
    }

    private function hasBinaryDiffAttribute(string $path): bool
    {
        $result = $this->gitResult(['check-attr', '-z', 'diff', '--', $path], $this->candidatePath);
        $parts = explode("\0", $result->getOutput());

        return in_array($parts[2] ?? '', ['binary', 'unset'], true);
    }

    private function conflictMarkerSize(string $path): int
    {
        $result = $this->gitResult(['check-attr', '-z', 'conflict-marker-size', '--', $path], $this->candidatePath);
        if ($result->getExitCode() !== 0) {
            throw new ContentSyncFailure('The candidate conflict marker settings could not be read safely. It was left untouched.');
        }
        $parts = explode("\0", $result->getOutput());
        $value = $parts[2] ?? null;
        if (in_array($value, ['unspecified', 'unset'], true)) {
            return 7;
        }
        if (! is_string($value) || preg_match('/^[0-9]{1,3}$/', $value) !== 1) {
            throw new ContentSyncFailure('The candidate conflict marker settings are unsupported. It was left untouched.');
        }
        $size = (int) $value;
        if ($size < 1 || $size > 256) {
            throw new ContentSyncFailure('The candidate conflict marker settings are unsupported. It was left untouched.');
        }

        return $size;
    }

    /** @return array<int, array{mode: string, blob: string}> */
    private function unmergedStages(string $path): array
    {
        $output = $this->gitResult(['ls-files', '-u', '-z', '--', $this->literalPathspec($path)], $this->candidatePath)->getOutput();
        $stages = [];
        foreach (explode("\0", $output) as $entry) {
            if (preg_match('/^([0-7]{6}) ([a-f0-9]{40,64}) ([123])\t/', $entry, $matches) === 1) {
                $stages[(int) $matches[3]] = ['mode' => $matches[1], 'blob' => $matches[2]];
            }
        }

        return $stages;
    }

    private function isSafeConflictPath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1 || ! $this->isEditorialPath($path)) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    private function isPrivateCvGlobalPath(string $path): bool
    {
        return preg_match('#^content/globals/(?:[^/]+/)?cv\.yaml$#', $path) === 1;
    }

    private function candidateFilePath(string $path): string
    {
        if (! $this->isSafeConflictPath($path)) {
            throw new ContentSyncFailure('The candidate conflict path is not supported. It was left untouched.');
        }

        $root = realpath($this->candidatePath);
        $parent = realpath(dirname($this->candidatePath.'/'.$path));
        if (! is_string($root) || ! is_string($parent) || ! str_starts_with($parent, $root.DIRECTORY_SEPARATOR)) {
            throw new ContentSyncFailure('The candidate conflict path resolves outside its worktree. It was left untouched.');
        }

        $fullPath = $parent.DIRECTORY_SEPARATOR.basename($path);
        if (is_link($fullPath)) {
            throw new ContentSyncFailure('The candidate conflict path is a symbolic link. It was left untouched.');
        }

        return $fullPath;
    }

    private function writeCandidateFile(string $path, ?string $contents, ?string $gitMode): void
    {
        $fullPath = $this->candidateFilePath($path);
        if ($contents === null) {
            if ((is_file($fullPath) || is_link($fullPath)) && ! unlink($fullPath)) {
                throw new ContentSyncFailure('The resolved candidate file could not be removed. It was left isolated from live content.');
            }

            return;
        }
        $mode = is_string($gitMode)
            ? (octdec($gitMode) & 0777)
            : (is_file($fullPath) ? ((fileperms($fullPath) ?: 0644) & 0777) : 0644);

        $temporary = dirname($fullPath).'/.content-sync-resolution-'.bin2hex(random_bytes(8)).'.tmp';
        if (file_put_contents($temporary, $contents, LOCK_EX) === false || ! chmod($temporary, $mode) || ! rename($temporary, $fullPath)) {
            @unlink($temporary);
            throw new ContentSyncFailure('The resolved candidate file could not be written. It was left isolated from live content.');
        }
    }

    /** @return array{backup: string, index_path: string, mode: int} */
    private function backupCandidateIndex(): array
    {
        $indexPath = trim($this->git(['rev-parse', '--git-path', 'index'], $this->candidatePath)->getOutput());
        if (! str_starts_with($indexPath, DIRECTORY_SEPARATOR)) {
            $indexPath = $this->candidatePath.DIRECTORY_SEPARATOR.$indexPath;
        }
        $indexPath = realpath($indexPath) ?: '';
        if ($indexPath === '' || ! is_file($indexPath)) {
            throw new ContentSyncFailure('Could not preserve the candidate Git index. It was left untouched.');
        }

        $directory = (string) config('content-sync.recovery_path', storage_path('app/private/content-sync-conflicts'));
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new ContentSyncFailure('Could not prepare a private candidate recovery file. The candidate was left untouched.');
        }
        if (is_link($directory)) {
            throw new ContentSyncFailure('The private candidate recovery directory is not safe. The candidate was left untouched.');
        }
        @chmod($directory, 0700);
        $backup = $directory.'/index-'.bin2hex(random_bytes(16));
        if (! copy($indexPath, $backup)) {
            throw new ContentSyncFailure('Could not preserve the candidate Git index. It was left untouched.');
        }
        @chmod($backup, 0600);

        return ['backup' => $backup, 'index_path' => $indexPath, 'mode' => (fileperms($indexPath) ?: 0644) & 0777];
    }

    /** @param array{backup: string, index_path: string, mode: int} $backup */
    private function restoreCandidateIndex(array $backup): void
    {
        $temporary = $backup['index_path'].'.restore-'.bin2hex(random_bytes(8));
        if (! copy($backup['backup'], $temporary) || ! chmod($temporary, $backup['mode']) || ! rename($temporary, $backup['index_path'])) {
            @unlink($temporary);
            throw new ContentSyncFailure('The unresolved candidate index could not be restored.');
        }
    }

    /** @return array{existed: bool, backup: string|null, mode: int} */
    private function backupCandidateFile(string $path): array
    {
        $fullPath = $this->candidateFilePath($path);
        if (! is_file($fullPath)) {
            return ['existed' => false, 'backup' => null, 'mode' => 0644];
        }

        $directory = (string) config('content-sync.recovery_path', storage_path('app/private/content-sync-conflicts'));
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new ContentSyncFailure('Could not prepare a private candidate recovery file. The candidate was left untouched.');
        }
        if (is_link($directory)) {
            throw new ContentSyncFailure('The private candidate recovery directory is not safe. The candidate was left untouched.');
        }
        @chmod($directory, 0700);
        $backup = $directory.'/'.bin2hex(random_bytes(16));
        if (! copy($fullPath, $backup)) {
            throw new ContentSyncFailure('Could not preserve the unresolved candidate. It was left untouched.');
        }
        @chmod($backup, 0600);

        return ['existed' => true, 'backup' => $backup, 'mode' => (fileperms($fullPath) ?: 0644) & 0777];
    }

    /** @param array{existed: bool, backup: string|null, mode: int} $backup */
    private function restoreCandidateFile(string $path, array $backup): void
    {
        $fullPath = $this->candidateFilePath($path);
        if (! $backup['existed']) {
            if (file_exists($fullPath) || is_link($fullPath)) {
                if (! is_file($fullPath) || is_link($fullPath) || ! @unlink($fullPath)
                    || file_exists($fullPath) || is_link($fullPath)) {
                    throw new ContentSyncFailure('The unresolved candidate could not be removed. Live content remains unchanged.');
                }
            }

            return;
        }

        $temporary = dirname($fullPath).'/.content-sync-restore-'.bin2hex(random_bytes(8)).'.tmp';
        if (! is_string($backup['backup']) || ! copy($backup['backup'], $temporary) || ! chmod($temporary, $backup['mode']) || ! rename($temporary, $fullPath)) {
            @unlink($temporary);
            throw new ContentSyncFailure('The unresolved candidate could not be restored. Live content remains unchanged.');
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
        $result = $this->gitResult(['diff', '--name-only', '--diff-filter=U', '-z'], $cwd);

        return array_values(array_filter(explode("\0", $result->getOutput()), static fn (string $path): bool => $path !== ''));
    }

    /** @return list<string> */
    private function changedPathsBetweenTreeAndHead(string $head, string $tree, string $cwd): array
    {
        $result = $this->gitResult(['diff', '--no-renames', '--name-only', '-z', $head, $tree], $cwd);
        if ($result->getExitCode() !== 0) {
            throw new ContentSyncFailure('The pinned candidate tree paths could not be identified. The candidate was left untouched.');
        }

        return array_values(array_filter(explode("\0", $result->getOutput()), static fn (string $path): bool => $path !== ''));
    }

    /** @return list<string> */
    private function candidateMergeHeads(string $cwd): array
    {
        $result = $this->gitResult(['rev-parse', '--quiet', '--verify', 'MERGE_HEAD'], $cwd);
        if ($result->getExitCode() !== 0) {
            return [];
        }

        $heads = array_values(array_filter(preg_split('/\R/', trim($result->getOutput())) ?: [], static fn (string $head): bool => $head !== ''));
        foreach ($heads as $head) {
            if (preg_match('/^(?:[a-f0-9]{40}|[a-f0-9]{64})$/', $head) !== 1) {
                throw new ContentSyncFailure('The isolated candidate merge state could not be verified. Manual inspection is required.');
            }
        }

        return $heads;
    }

    private function candidateIndexTree(string $cwd): string
    {
        $tree = trim($this->git(['write-tree'], $cwd)->getOutput());
        if (preg_match('/^(?:[a-f0-9]{40}|[a-f0-9]{64})$/', $tree) !== 1) {
            throw new ContentSyncFailure('The staged candidate tree could not be verified. Manual inspection is required.');
        }

        return $tree;
    }

    private function candidateIndexMode(string $path): ?string
    {
        $result = $this->gitResult(['ls-files', '-s', '-z', '--', $this->literalPathspec($path)], $this->candidatePath);
        if ($result->getExitCode() !== 0) {
            throw new ContentSyncFailure('The selected candidate file mode could not be verified safely.');
        }
        $records = array_values(array_filter(explode("\0", $result->getOutput()), static fn (string $record): bool => $record !== ''));
        if ($records === []) {
            return null;
        }
        if (count($records) !== 1 || preg_match('/^([0-7]{6}) [a-f0-9]{40,64} 0\t(.+)$/s', $records[0], $matches) !== 1 || $matches[2] !== $path) {
            throw new ContentSyncFailure('The selected candidate file mode could not be verified safely.');
        }

        return $matches[1];
    }

    /** @param array{contents: string|null, mode: string|null} $resolution */
    private function candidateFileMatchesResolution(string $path, array $resolution): bool
    {
        $fullPath = $this->candidateFilePath($path);
        if ($resolution['contents'] === null) {
            return ! file_exists($fullPath) && ! is_link($fullPath);
        }
        if (is_link($fullPath) || ! is_file($fullPath) || ! is_string($resolution['mode'])) {
            return false;
        }
        $size = filesize($fullPath);
        if (! is_int($size) || $size !== strlen($resolution['contents'])) {
            return false;
        }
        $actual = file_get_contents($fullPath);
        if (! is_string($actual) || ! hash_equals(hash('sha256', $resolution['contents']), hash('sha256', $actual))) {
            return false;
        }
        $executable = (fileperms($fullPath) & 0111) !== 0;

        return $executable === ($resolution['mode'] === '100755');
    }

    /** @param list<string> $expectedParents */
    private function candidateCommitMatches(string $expectedTree, array $expectedParents): bool
    {
        if (preg_match('/^(?:[a-f0-9]{40}|[a-f0-9]{64})$/', $expectedTree) !== 1
            || $this->candidateMergeHeads($this->candidatePath) !== []
            || $this->unmergedPaths($this->candidatePath) !== []
            || $this->workingTreeStatus($this->candidatePath) !== []
            || $this->candidateIndexTree($this->candidatePath) !== $expectedTree) {
            return false;
        }

        $result = $this->gitResult(['show', '-s', '--format=%T%n%P', 'HEAD'], $this->candidatePath);
        if ($result->getExitCode() !== 0) {
            return false;
        }
        $lines = preg_split('/\R/', trim($result->getOutput()), 2) ?: [];
        if (count($lines) !== 2 || ! hash_equals($expectedTree, $lines[0])) {
            return false;
        }
        $parents = $lines[1] === '' ? [] : (preg_split('/\s+/', $lines[1]) ?: []);

        return $parents === $expectedParents;
    }

    private function literalPathspec(string $path): string
    {
        return ':(literal)'.$path;
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
