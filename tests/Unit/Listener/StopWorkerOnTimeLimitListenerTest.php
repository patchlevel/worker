<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Tests\Unit\Listener;

use Patchlevel\Worker\Event\WorkerRunningEvent;
use Patchlevel\Worker\Listener\StopWorkerOnTimeLimitListener;
use Patchlevel\Worker\Worker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sleep;

#[CoversClass(StopWorkerOnTimeLimitListener::class)]
final class StopWorkerOnTimeLimitListenerTest extends TestCase
{
    public function testShouldNotStop(): void
    {
        $worker = $this->createMock(Worker::class);
        $worker
            ->expects($this->never())
            ->method('stop');

        $listener = new StopWorkerOnTimeLimitListener(10);
        $listener->onWorkerStarted();
        $listener->onWorkerRunning(new WorkerRunningEvent($worker));
    }

    public function testShouldStop(): void
    {
        $worker = $this->createMock(Worker::class);
        $worker
            ->expects($this->once())
            ->method('stop');

        $listener = new StopWorkerOnTimeLimitListener(1);
        $listener->onWorkerStarted();

        sleep(2);

        $listener->onWorkerRunning(new WorkerRunningEvent($worker));
    }
}
