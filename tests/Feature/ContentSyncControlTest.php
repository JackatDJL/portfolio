<?php

namespace Tests\Feature;

use App\Support\ContentSyncControl;
use Tests\TestCase;

class ContentSyncControlTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/content-sync-control-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0770, true);
        config()->set('content-sync.control_state_path', $this->directory.'/state.json');
        config()->set('content-sync.control_log_path', $this->directory.'/sync.log');
        config()->set('content-sync.lock_path', $this->directory.'/sync.lock');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) unlink($file);
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
        $this->assertSame(0, \Carbon\CarbonImmutable::parse($status['next_run_at'])->minute % 5);

        flock($syncLock, LOCK_UN);
        fclose($syncLock);
        $control->finish('Lauf erfolgreich abgeschlossen.');
        $status = $control->status();
        $this->assertFalse($status['running']);
        $this->assertStringContainsString('Lauf erfolgreich abgeschlossen.', $status['logs']);
    }
}
