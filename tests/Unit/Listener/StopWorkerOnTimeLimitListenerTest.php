<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Tests\Unit\Listener;

use Patchlevel\Worker\Event\WorkerRunningEvent;
use Patchlevel\Worker\Listener\StopWorkerOnTimeLimitListener;
use Patchlevel\Worker\Worker;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

use function sleep;

final class StopWorkerOnTimeLimitListenerTest extends TestCase
{
    use ProphecyTrait;

    public function testShouldNotStop(): void
    {
        $workerMock = $this->prophesize(Worker::class);
        $workerMock->stop()->shouldNotBeCalled();
        $worker = $workerMock->reveal();

        $listener = new StopWorkerOnTimeLimitListener(10);
        $listener->onWorkerStarted();
        $listener->onWorkerRunning(new WorkerRunningEvent($worker));
    }

    public function testShouldStop(): void
    {
        $workerMock = $this->prophesize(Worker::class);
        $workerMock->stop()->shouldBeCalled();
        $worker = $workerMock->reveal();

        $listener = new StopWorkerOnTimeLimitListener(1);
        $listener->onWorkerStarted();

        sleep(1);

        $listener->onWorkerRunning(new WorkerRunningEvent($worker));
    }
}
