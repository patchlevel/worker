<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Tests\Unit\Listener;

use Patchlevel\Worker\Event\WorkerRunningEvent;
use Patchlevel\Worker\Listener\StopWorkerOnIterationLimitListener;
use Patchlevel\Worker\Worker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(StopWorkerOnIterationLimitListener::class)]
final class StopWorkerOnIterationLimitListenerTest extends TestCase
{
    public function testShouldNotStop(): void
    {
        $worker = $this->createMock(Worker::class);
        $worker
            ->expects($this->never())
            ->method('stop');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->never())
            ->method('info')
            ->with('Worker stopped due to maximum iteration of {count}', ['count' => 10]);

        $listener = new StopWorkerOnIterationLimitListener(10, $logger);
        $listener->onWorkerRunning(new WorkerRunningEvent($worker));
    }

    public function testShouldStop(): void
    {
        $worker = $this->createMock(Worker::class);
        $worker
            ->expects($this->once())
            ->method('stop');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('info')
            ->with('Worker stopped due to maximum iteration of {count}', ['count' => 1]);

        $listener = new StopWorkerOnIterationLimitListener(1, $logger);
        $listener->onWorkerRunning(new WorkerRunningEvent($worker));
    }
}
