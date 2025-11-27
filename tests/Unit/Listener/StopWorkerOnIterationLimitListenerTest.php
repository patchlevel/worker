<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Tests\Unit\Listener;

use Patchlevel\Worker\Event\WorkerRunningEvent;
use Patchlevel\Worker\Listener\StopWorkerOnIterationLimitListener;
use Patchlevel\Worker\Worker;
use PHPUnit\Framework\TestCase;

final class StopWorkerOnIterationLimitListenerTest extends TestCase
{
    public function testShouldNotStop(): void
    {
        $worker = $this->createMock(Worker::class);
        $worker->expects($this->never())->method('stop');

        $listener = new StopWorkerOnIterationLimitListener(10);
        $listener->onWorkerRunning(new WorkerRunningEvent($worker));
    }

    public function testShouldStop(): void
    {
        $worker = $this->createMock(Worker::class);
        $worker->expects($this->once())->method('stop');

        $listener = new StopWorkerOnIterationLimitListener(1);
        $listener->onWorkerRunning(new WorkerRunningEvent($worker));
    }
}
