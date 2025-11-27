<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Tests\Unit\Listener;

use Patchlevel\Worker\Bytes;
use Patchlevel\Worker\Event\WorkerRunningEvent;
use Patchlevel\Worker\Listener\StopWorkerOnMemoryLimitListener;
use Patchlevel\Worker\Worker;
use PHPUnit\Framework\TestCase;

final class StopWorkerOnMemoryLimitListenerTest extends TestCase
{
    public function testShouldNotStop(): void
    {
        $worker = $this->createMock(Worker::class);
        $worker->expects($this->never())->method('stop');

        $listener = new StopWorkerOnMemoryLimitListener(Bytes::parseFromString('5GB'));
        $listener->onWorkerRunning(new WorkerRunningEvent($worker));
    }

    public function testShouldStop(): void
    {
        $worker = $this->createMock(Worker::class);
        $worker->expects($this->once())->method('stop');

        $listener = new StopWorkerOnMemoryLimitListener(Bytes::parseFromString('1KB'));
        $listener->onWorkerRunning(new WorkerRunningEvent($worker));
    }
}
