<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class VttTelemetryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vtt-tel-'.uniqid('', true);
        mkdir($this->dir, 0777, true);
        require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR.'telemetry.php';
        vttTelemetrySetDir($this->dir);
        $_SERVER['HTTP_X_VTT_CLIENT_ID'] = 'client-test-12345';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SESSION = ['vtt_is_gm' => false];
    }

    protected function tearDown(): void
    {
        vttTelemetrySetDir(null);
        if (is_dir($this->dir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->dir);
        }
        parent::tearDown();
    }

    public function test_login_writes_event_and_presence(): void
    {
        vttTelemetryRecordLogin(true, false);

        $events = file($this->dir.DIRECTORY_SEPARATOR.'events.jsonl', FILE_IGNORE_NEW_LINES);
        $this->assertNotFalse($events);
        $this->assertNotEmpty($events);
        $decoded = json_decode($events[0], true);
        $this->assertSame('login', $decoded['type']);
        $this->assertSame('player', $decoded['role']);
        $this->assertSame('client-test-12345', $decoded['clientId']);

        $presence = json_decode((string) file_get_contents($this->dir.DIRECTORY_SEPARATOR.'presence.json'), true);
        $this->assertArrayHasKey('client-test-12345', $presence['clients']);
        $this->assertNotEmpty($presence['openSession']);
    }

    public function test_failed_login_is_recorded(): void
    {
        vttTelemetryRecordLogin(false, true);
        $line = json_decode((string) file($this->dir.DIRECTORY_SEPARATOR.'events.jsonl')[0], true);
        $this->assertSame('login_fail', $line['type']);
        $this->assertSame('gm', $line['role']);
    }

    public function test_interaction_and_presence_touch(): void
    {
        vttTelemetryRecordInteraction('move-token');
        vttTelemetryTouchPresence('check');
        $line = json_decode(trim((string) file_get_contents($this->dir.DIRECTORY_SEPARATOR.'events.jsonl')), true);
        $this->assertSame('interaction', $line['type']);
        $this->assertSame('move-token', $line['action']);
        $this->assertFileExists($this->dir.DIRECTORY_SEPARATOR.'presence.json');
    }

    public function test_event_log_rotates_when_too_long(): void
    {
        $path = $this->dir.DIRECTORY_SEPARATOR.'events.jsonl';
        $big = str_repeat(json_encode(['type' => 'interaction', 'action' => 'x', 'ts' => time()])."\n", 9000);
        file_put_contents($path, $big);
        vttTelemetryAppendEvent('login', ['role' => 'player', 'clientId' => 'client-test-12345']);
        $count = count(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
        $this->assertLessThan(9000, $count);
        $this->assertGreaterThan(100, $count);
    }

    public function test_player_name_decodes_percent_encoded_unicode(): void
    {
        $_SERVER['HTTP_X_VTT_PLAYER_NAME'] = rawurlencode('Michał');
        $this->assertSame('Michał', vttTelemetryPlayerName());
    }
}
