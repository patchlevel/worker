<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Tests\Unit\Listener;

use Patchlevel\Worker\Listener\HeartbeatListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

use function clearstatcache;
use function filemtime;
use function is_file;
use function sys_get_temp_dir;
use function time;
use function touch;
use function uniqid;
use function unlink;

#[CoversClass(HeartbeatListener::class)]
final class HeartbeatListenerTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/' . uniqid('worker-heartbeat-', true);
    }

    protected function tearDown(): void
    {
        if (!is_file($this->file)) {
            return;
        }

        unlink($this->file);
    }

    public function testCreateFileOnStart(): void
    {
        $listener = new HeartbeatListener($this->file);
        $listener->onWorkerStarted();

        self::assertFileExists($this->file);
    }

    public function testUpdateFileAfterIteration(): void
    {
        touch($this->file, time() - 60);

        $listener = new HeartbeatListener($this->file);
        $listener->onWorkerRunning();

        clearstatcache(true, $this->file);

        self::assertGreaterThanOrEqual(time() - 1, filemtime($this->file));
    }

    public function testRemoveFileOnStop(): void
    {
        touch($this->file);

        $listener = new HeartbeatListener($this->file);
        $listener->onWorkerStopped();

        self::assertFileDoesNotExist($this->file);
    }

    public function testStopWithoutFile(): void
    {
        $listener = new HeartbeatListener($this->file);
        $listener->onWorkerStopped();

        self::assertFileDoesNotExist($this->file);
    }

    public function testLogWarningIfFileCannotBeWritten(): void
    {
        $file = sys_get_temp_dir() . '/' . uniqid('worker-heartbeat-', true) . '/missing-dir/heartbeat';

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with('Worker could not update heartbeat file {file}', ['file' => $file]);

        $listener = new HeartbeatListener($file, $logger);
        $listener->onWorkerRunning();

        self::assertFileDoesNotExist($file);
    }
}
