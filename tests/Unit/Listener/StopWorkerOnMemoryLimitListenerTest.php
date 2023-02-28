<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Tests\Unit\Listener;

use Patchlevel\Worker\Bytes;
use Patchlevel\Worker\Event\WorkerRunningEvent;
use Patchlevel\Worker\Listener\StopWorkerOnMemoryLimitListener;
use Patchlevel\Worker\Worker;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

final class StopWorkerOnMemoryLimitListenerTest extends TestCase
{
    use ProphecyTrait;

    public function testShouldNotStop(): void
    {
        $workerMock = $this->prophesize(Worker::class);
        $workerMock->stop()->shouldNotBeCalled();
        $worker = $workerMock->reveal();

        $listener = new StopWorkerOnMemoryLimitListener(Bytes::parseFromString('5GB'));
        $listener->onWorkerRunning(new WorkerRunningEvent($worker));
    }

    public function testShouldStop(): void
    {
        $workerMock = $this->prophesize(Worker::class);
        $workerMock->stop()->shouldBeCalled();
        $worker = $workerMock->reveal();

        $listener = new StopWorkerOnMemoryLimitListener(Bytes::parseFromString('1KB'));
        $listener->onWorkerRunning(new WorkerRunningEvent($worker));
    }
}
