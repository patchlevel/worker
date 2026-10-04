<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Tests\Unit\Listener;

use Patchlevel\Worker\Event\WorkerRunningEvent;
use Patchlevel\Worker\Listener\StopWorkerOnRestartSignalListener;
use Patchlevel\Worker\Worker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

use function is_file;
use function sys_get_temp_dir;
use function time;
use function touch;
use function uniqid;
use function unlink;

#[CoversClass(StopWorkerOnRestartSignalListener::class)]
final class StopWorkerOnRestartSignalListenerTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/' . uniqid('worker-restart-', true);
    }

    protected function tearDown(): void
    {
        if (!is_file($this->file)) {
            return;
        }

        unlink($this->file);
    }

    public function testShouldNotStopWithoutFile(): void
    {
        $worker = $this->createMock(Worker::class);
        $worker
            ->expects($this->never())
            ->method('stop');

        $listener = new StopWorkerOnRestartSignalListener($this->file);
        $listener->onWorkerStarted();
        $listener->onWorkerRunning(new WorkerRunningEvent($worker));
    }

    public function testShouldNotStopIfFileWasTouchedBeforeStart(): void
    {
        $worker = $this->createMock(Worker::class);
        $worker
            ->expects($this->never())
            ->method('stop');

        touch($this->file, time() - 10);

        $listener = new StopWorkerOnRestartSignalListener($this->file);
        $listener->onWorkerStarted();
        $listener->onWorkerRunning(new WorkerRunningEvent($worker));
    }

    public function testShouldStopIfFileWasTouchedAfterStart(): void
    {
        $worker = $this->createMock(Worker::class);
        $worker
            ->expects($this->once())
            ->method('stop');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('info')
            ->with('Worker stopped due to restart signal from {file}', ['file' => $this->file]);

        $listener = new StopWorkerOnRestartSignalListener($this->file, $logger);
        $listener->onWorkerStarted();

        touch($this->file, time() + 10);

        $listener->onWorkerRunning(new WorkerRunningEvent($worker));
    }
}
