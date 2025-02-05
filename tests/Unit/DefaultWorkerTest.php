<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Tests\Unit;

use Patchlevel\Worker\DefaultWorker;
use Patchlevel\Worker\Event\WorkerRunningEvent;
use Patchlevel\Worker\Event\WorkerStartedEvent;
use Patchlevel\Worker\Event\WorkerStoppedEvent;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class DefaultWorkerTest extends TestCase
{
    use ProphecyTrait;

    public function testRunWorker(): void
    {
        $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
        $eventDispatcher->dispatch(Argument::type(WorkerStartedEvent::class))->shouldBeCalledTimes(1);
        $eventDispatcher->dispatch(Argument::type(WorkerRunningEvent::class))->shouldBeCalledTimes(1)->will(
            /** @param array{WorkerRunningEvent} $args */
            static function (array $args) {
                $args[0]->worker->stop();

                return $args[0];
            },
        );
        $eventDispatcher->dispatch(Argument::type(WorkerStoppedEvent::class))->shouldBeCalledTimes(1);

        $logger = $this->prophesize(LoggerInterface::class);
        $logger->debug('Worker starting')->shouldBeCalledTimes(1);
        $logger->debug('Worker starting job run')->shouldBeCalledTimes(1);
        $logger->debug('Worker finished job run ({ranTime}ms)', Argument::any())->shouldBeCalledTimes(1);
        $logger->debug('Worker received stop signal')->shouldBeCalledTimes(1);
        $logger->debug('Worker stopped')->shouldBeCalledTimes(1);
        $logger->debug('Worker terminated')->shouldBeCalledTimes(1);

        $worker = new DefaultWorker(static fn () => null, $eventDispatcher->reveal(), $logger->reveal());
        $worker->run(200);
    }

    public function testJobStopWorker(): void
    {
        $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
        $eventDispatcher->dispatch(Argument::type(WorkerStartedEvent::class))->shouldBeCalledTimes(1);
        $eventDispatcher->dispatch(Argument::type(WorkerRunningEvent::class))->shouldBeCalledTimes(1);
        $eventDispatcher->dispatch(Argument::type(WorkerStoppedEvent::class))->shouldBeCalledTimes(1);

        $logger = $this->prophesize(LoggerInterface::class);
        $logger->debug('Worker starting')->shouldBeCalledTimes(1);
        $logger->debug('Worker starting job run')->shouldBeCalledTimes(1);
        $logger->debug('Worker finished job run ({ranTime}ms)', Argument::any())->shouldBeCalledTimes(1);
        $logger->debug('Worker received stop signal')->shouldBeCalledTimes(1);
        $logger->debug('Worker stopped')->shouldBeCalledTimes(1);
        $logger->debug('Worker terminated')->shouldBeCalledTimes(1);

        $worker = new DefaultWorker(
            static function ($stop): void {
                $stop();
            },
            $eventDispatcher->reveal(),
            $logger->reveal(),
        );

        $worker->run(0);
    }

    public function testCustomEventDispatcher(): void
    {
        $listener = new class {
            public int $called = 0;

            public function __invoke(WorkerStartedEvent $event): void
            {
                $this->called++;
            }
        };

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(WorkerStartedEvent::class, $listener);

        $logger = $this->prophesize(LoggerInterface::class);
        $worker = DefaultWorker::create(
            static function ($stop): void {
                $stop();
            },
            [],
            $logger->reveal(),
            $eventDispatcher,
        );

        $worker->run(0);

        self::assertEquals(1, $listener->called);
    }
}
