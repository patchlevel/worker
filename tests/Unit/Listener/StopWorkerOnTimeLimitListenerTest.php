<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Tests\Unit\Listener;

use Patchlevel\Worker\Event\WorkerRunningEvent;
use Patchlevel\Worker\Listener\StopWorkerOnTimeLimitListener;
use Patchlevel\Worker\Tests\TestClock;
use Patchlevel\Worker\Worker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(StopWorkerOnTimeLimitListener::class)]
final class StopWorkerOnTimeLimitListenerTest extends TestCase
{
    public function testShouldNotStopBeforeTimeLimit(): void
    {
        $worker = $this->createMock(Worker::class);
        $worker
            ->expects($this->never())
            ->method('stop');

        $clock = new TestClock();

        $listener = new StopWorkerOnTimeLimitListener(10, clock: $clock);
        $listener->onWorkerStarted();

        $clock->advance(9_999);

        $listener->onWorkerRunning(new WorkerRunningEvent($worker));
    }

    public function testShouldStopWhenTimeLimitReached(): void
    {
        $worker = $this->createMock(Worker::class);
        $worker
            ->expects($this->once())
            ->method('stop');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('info')
            ->with('Worker stopped due to time limit of {timeLimit}s exceeded', ['timeLimit' => 10]);

        $clock = new TestClock();

        $listener = new StopWorkerOnTimeLimitListener(10, $logger, $clock);
        $listener->onWorkerStarted();

        $clock->advance(10_000);

        $listener->onWorkerRunning(new WorkerRunningEvent($worker));
    }
}
