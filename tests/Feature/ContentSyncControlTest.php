<?php

namespace Tests\Feature;

use App\Support\ContentSyncControl;
use Carbon\CarbonImmutable;
use Statamic\Facades\Role;
use Statamic\Facades\User;
use Tests\TestCase;

class ContentSyncControlTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('statamic.editions.pro', true);
        $this->directory = sys_get_temp_dir().'/content-sync-control-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0770, true);
        config()->set('content-sync.control_state_path', $this->directory.'/state.json');
        config()->set('content-sync.control_log_path', $this->directory.'/sync.log');
        config()->set('content-sync.lock_path', $this->directory.'/sync.lock');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    public function test_it_reports_live_state_logs_and_next_regular_tick(): void
    {
        $control = app(ContentSyncControl::class);
        $control->begin();
        $syncLock = fopen((string) config('content-sync.lock_path'), 'c');
        flock($syncLock, LOCK_EX | LOCK_NB);

        $status = $control->status();
        $this->assertTrue($status['running']);
        $this->assertNotEmpty($status['started_at']);
        $this->assertStringContainsString('Lauf gestartet.', $status['logs']);
        $this->assertSame(0, CarbonImmutable::parse($status['next_run_at'])->minute % 5);

        flock($syncLock, LOCK_UN);
        fclose($syncLock);
        $control->finish('Lauf erfolgreich abgeschlossen.');
        $status = $control->status();
        $this->assertFalse($status['running']);
        $this->assertStringContainsString('Lauf erfolgreich abgeschlossen.', $status['logs']);
    }

    public function test_a_busy_overlapping_invocation_cannot_remove_the_active_run_state(): void
    {
        $control = app(ContentSyncControl::class);
        $activePid = getmypid();
        $busyPid = $activePid + 100000;
        $control->begin($activePid);
        $control->begin($busyPid);

        $control->finish('Übersprungen: Ein anderer Lauf hält bereits den Sync-Lock.', $busyPid);

        $status = $control->status();
        $this->assertTrue($status['running']);
        $this->assertNotEmpty($status['started_at']);
        $control->finish('Lauf erfolgreich abgeschlossen.', $activePid);
    }

    public function test_it_prunes_state_when_a_pid_is_reused_by_an_unrelated_process(): void
    {
        $control = app(ContentSyncControl::class);
        $pid = getmypid();
        $control->begin($pid);
        $statePath = (string) config('content-sync.control_state_path');
        $state = json_decode((string) file_get_contents($statePath), true, flags: JSON_THROW_ON_ERROR);
        $state['runs'][(string) $pid]['process_identity'] = 'previous-boot:old-process-start';
        file_put_contents($statePath, json_encode($state, JSON_THROW_ON_ERROR));

        $status = $control->status();

        $this->assertFalse($status['running']);
        $this->assertNull($status['started_at']);
        $this->assertFileDoesNotExist($statePath);
    }

    public function test_log_reads_and_storage_stay_bounded(): void
    {
        $logPath = (string) config('content-sync.control_log_path');
        file_put_contents($logPath, str_repeat("old log line\n", 120000));
        app(ContentSyncControl::class)->log('latest log marker');

        $status = app(ContentSyncControl::class)->status();

        $this->assertLessThanOrEqual(1_048_576, filesize($logPath));
        $this->assertLessThanOrEqual(65_536, strlen($status['logs']));
        $this->assertStringContainsString('latest log marker', $status['logs']);
    }

    public function test_log_compaction_waits_until_a_concurrent_writer_releases_the_shared_lock(): void
    {
        $logPath = (string) config('content-sync.control_log_path');
        file_put_contents($logPath, str_repeat("older log line\n", 120000));
        $lock = fopen($logPath.'.lock', 'c');
        flock($lock, LOCK_SH);
        file_put_contents($logPath, "[writer] latest concurrent marker\n", FILE_APPEND);

        $statusDuringWrite = app(ContentSyncControl::class)->status();

        $this->assertGreaterThan(1_048_576, filesize($logPath));
        $this->assertStringContainsString('latest concurrent marker', $statusDuringWrite['logs']);

        flock($lock, LOCK_UN);
        fclose($lock);

        $statusAfterWrite = app(ContentSyncControl::class)->status();

        $this->assertLessThanOrEqual(1_048_576, filesize($logPath));
        $this->assertStringContainsString('latest concurrent marker', $statusAfterWrite['logs']);
    }

    public function test_post_run_compaction_bounds_the_log_and_releases_the_manual_wrapper_state(): void
    {
        $logPath = (string) config('content-sync.control_log_path');
        file_put_contents($logPath, str_repeat("older log line\n", 120000));
        $control = app(ContentSyncControl::class);
        $wrapperPid = getmypid();
        $lock = fopen($logPath.'.lock', 'c');
        flock($lock, LOCK_SH);
        $control->begin($wrapperPid);
        $control->log('latest post-run marker');

        $this->assertGreaterThan(1_048_576, filesize($logPath));

        flock($lock, LOCK_UN);
        fclose($lock);

        $this->artisan('portfolio:content-sync:compact-log', ['--run-pid' => $wrapperPid])->assertExitCode(0);

        $status = $control->status();
        $this->assertLessThanOrEqual(1_048_576, filesize($logPath));
        $this->assertStringContainsString('latest post-run marker', $status['logs']);
        $this->assertFalse($status['running']);
    }

    public function test_only_superusers_can_open_the_content_sync_control_page(): void
    {
        $this->actingAs(User::make()->id('content-sync-cp-test')->email('content-sync@example.invalid')->makeSuper());
        $this->get('/cp/content-sync')->assertOk()->assertSee('Nächster regulärer Lauf');
        $this->get('/cp/content-sync/status')->assertOk()->assertJsonPath('conflicts.files', []);

        $rolesPath = resource_path('users/roles.yaml');
        $originalRoles = file_get_contents($rolesPath);
        $role = Role::make()->handle('content-sync-limited-test')->title('Limited content sync test')->addPermission('access cp');
        Role::save($role);
        $limited = User::make()->id('content-sync-limited-test')->email('limited-content-sync@example.invalid')->assignRole($role);
        $limited->save();
        try {
            $this->actingAs($limited)->get('/cp/content-sync')->assertForbidden();
            $this->actingAs($limited)->get('/cp/content-sync/conflicts')->assertForbidden();
        } finally {
            $limited->delete();
            Role::delete($role);
            file_put_contents($rolesPath, $originalRoles);
        }
    }
}
