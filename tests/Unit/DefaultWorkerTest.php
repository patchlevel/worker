<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Tests\Unit;

use Patchlevel\Worker\DefaultWorker;
use Patchlevel\Worker\Event\WorkerRunningEvent;
use Patchlevel\Worker\Event\WorkerStartedEvent;
use Patchlevel\Worker\Event\WorkerStoppedEvent;
use Patchlevel\Worker\Tests\DummyLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use function array_column;

final class DefaultWorkerTest extends TestCase
{
    public function testRunWorker(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $seenEvents = [];

        $eventDispatcher
            ->expects(self::exactly(3))
            ->method('dispatch')
            ->willReturnCallback(
                static function (object $event) use (&$seenEvents): object {
                    $seenEvents[] = $event::class;

                    if ($event instanceof WorkerRunningEvent) {
                        $event->worker->stop();
                    }

                    return $event;
                },
            );

        $logger = new DummyLogger();

        $worker = new DefaultWorker(
            static fn () => null,
            $eventDispatcher,
            $logger,
        );

        $worker->run(200);

        self::assertSame(
            [
                WorkerStartedEvent::class,
                WorkerRunningEvent::class,
                WorkerStoppedEvent::class,
            ],
            $seenEvents,
        );

        $messages = array_column($logger->entries, 'message');

        self::assertSame(
            [
                'Worker starting',
                'Worker starting job run',
                'Worker finished job run ({ranTime}ms)',
                'Worker received stop signal',
                'Worker stopped',
                'Worker terminated',
            ],
            $messages,
        );

        self::assertArrayHasKey('ranTime', $logger->entries[2]['context']);
    }

    public function testJobStopWorker(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $seenEvents = [];

        $eventDispatcher
            ->expects(self::exactly(3))
            ->method('dispatch')
            ->willReturnCallback(
                static function (object $event) use (&$seenEvents): object {
                    $seenEvents[] = $event::class;

                    return $event;
                },
            );

        $logger = new DummyLogger();

        $worker = new DefaultWorker(
            static function (callable $stop): void {
                $stop();
            },
            $eventDispatcher,
            $logger,
        );

        $worker->run(0);

        self::assertSame(
            [
                WorkerStartedEvent::class,
                WorkerRunningEvent::class,
                WorkerStoppedEvent::class,
            ],
            $seenEvents,
        );

        $messages = array_column($logger->entries, 'message');

        self::assertSame(
            [
                'Worker starting',
                'Worker starting job run',
                'Worker received stop signal',
                'Worker finished job run ({ranTime}ms)',
                'Worker stopped',
                'Worker terminated',
            ],
            $messages,
        );

        self::assertArrayHasKey('ranTime', $logger->entries[3]['context']);
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

        $logger = new DummyLogger();

        $worker = DefaultWorker::create(
            static function (callable $stop): void {
                $stop();
            },
            [],
            $logger,
            $eventDispatcher,
        );

        $worker->run(0);

        self::assertSame(1, $listener->called);
    }
}
