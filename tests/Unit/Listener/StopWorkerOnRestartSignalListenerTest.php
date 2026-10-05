<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Tests\Unit\Listener;

use Patchlevel\Worker\Event\WorkerRunningEvent;
use Patchlevel\Worker\Event\WorkerStartedEvent;
use Patchlevel\Worker\Listener\StopWorkerOnRestartSignalListener;
use Patchlevel\Worker\Tests\TestClock;
use Patchlevel\Worker\Worker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

use function is_file;
use function proc_close;
use function proc_open;
use function sprintf;
use function sys_get_temp_dir;
use function touch;
use function uniqid;
use function unlink;
use function var_export;

use const PHP_BINARY;

#[CoversClass(StopWorkerOnRestartSignalListener::class)]
final class StopWorkerOnRestartSignalListenerTest extends TestCase
{
    private string $file;

    private TestClock $clock;

    private int $startTime;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/' . uniqid('worker-restart-', true);
        $this->clock = new TestClock();
        $this->startTime = $this->clock->now()->getTimestamp();
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

        $listener = new StopWorkerOnRestartSignalListener($this->file, clock: $this->clock);
        $listener->onWorkerStarted();
        $listener->onWorkerRunning(new WorkerRunningEvent($worker));
    }

    public function testShouldNotStopIfFileWasTouchedBeforeStart(): void
    {
        $worker = $this->createMock(Worker::class);
        $worker
            ->expects($this->never())
            ->method('stop');

        touch($this->file, $this->startTime - 10);

        $listener = new StopWorkerOnRestartSignalListener($this->file, clock: $this->clock);
        $listener->onWorkerStarted();
        $listener->onWorkerRunning(new WorkerRunningEvent($worker));
    }

    public function testShouldNotStopIfFileWasTouchedAtStart(): void
    {
        $worker = $this->createMock(Worker::class);
        $worker
            ->expects($this->never())
            ->method('stop');

        touch($this->file, $this->startTime);

        $listener = new StopWorkerOnRestartSignalListener($this->file, clock: $this->clock);
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

        touch($this->file, $this->startTime + 10);

        $listener = new StopWorkerOnRestartSignalListener($this->file, $logger, $this->clock);
        $listener->onWorkerStarted();
        $listener->onWorkerRunning(new WorkerRunningEvent($worker));
    }

    public function testShouldStopIfFileWasTouchedByAnotherProcess(): void
    {
        $worker = $this->createMock(Worker::class);
        $worker
            ->expects($this->once())
            ->method('stop');

        touch($this->file, $this->startTime - 10);

        $listener = new StopWorkerOnRestartSignalListener($this->file, clock: $this->clock);
        $listener->onWorkerStarted();

        // A deployment touches the file from another process, which doesn't clear the stat cache
        // of this process. The delay makes sure the first check below caches the old modification time.
        $process = proc_open(
            [PHP_BINARY, '-r', sprintf('usleep(200000); touch(%s, %d);', var_export($this->file, true), $this->startTime + 10)],
            [],
            $pipes,
        );
        self::assertIsResource($process);

        $listener->onWorkerRunning(new WorkerRunningEvent($worker));

        proc_close($process);

        $listener->onWorkerRunning(new WorkerRunningEvent($worker));
    }

    public function testSubscribedEvents(): void
    {
        self::assertSame(
            [
                WorkerStartedEvent::class => 'onWorkerStarted',
                WorkerRunningEvent::class => 'onWorkerRunning',
            ],
            StopWorkerOnRestartSignalListener::getSubscribedEvents(),
        );
    }
}
