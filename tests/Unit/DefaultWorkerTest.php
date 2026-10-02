<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Tests\Unit;

use Patchlevel\Worker\Bytes;
use Patchlevel\Worker\DefaultWorker;
use Patchlevel\Worker\Event\WorkerRunningEvent;
use Patchlevel\Worker\Event\WorkerStartedEvent;
use Patchlevel\Worker\Event\WorkerStoppedEvent;
use Patchlevel\Worker\Listener\StopWorkerOnIterationLimitListener;
use Patchlevel\Worker\Listener\StopWorkerOnMemoryLimitListener;
use Patchlevel\Worker\Listener\StopWorkerOnSignalListener;
use Patchlevel\Worker\Listener\StopWorkerOnTimeLimitListener;
use Patchlevel\Worker\Tests\ReturnCallback;
use Patchlevel\Worker\Tests\TestClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use function array_shift;

#[CoversClass(DefaultWorker::class)]
final class DefaultWorkerTest extends TestCase
{
    public function testRunWorker(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->exactly(3))
            ->method('dispatch')
            ->willReturnCallback(
                static function (object $event): object {
                    if ($event instanceof WorkerRunningEvent) {
                        $event->worker->stop();
                    }

                    return $event;
                },
            );

        $invokationCount = $this->exactly(6);
        $invokationParameters = [
            ['Worker starting', []],
            ['Worker starting job run', []],
            ['Worker finished job run ({ranTime}ms)', ['ranTime' => 0]],
            ['Worker received stop signal', []],
            ['Worker stopped', []],
            ['Worker terminated', []],
        ];

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($invokationCount)
            ->method('debug')
            ->willReturnCallback(function (...$parameters) use ($invokationCount, $invokationParameters): void {
                $this->assertSame($invokationParameters[$invokationCount->numberOfInvocations() - 1], $parameters);
            });

        $worker = new DefaultWorker(static fn () => null, $eventDispatcher, $logger);
        $worker->run(200);
    }

    public function testJobStopWorker(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->exactly(3))
            ->method('dispatch');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->exactly(6))
            ->method('debug')
            ->willReturnMap([
                ['Worker starting'],
                ['Worker starting job run'],
                ['Worker finished job run ({ranTime}ms)'],
                ['Worker received stop signal'],
                ['Worker stopped'],
                ['Worker terminated'],
            ]);

        $worker = new DefaultWorker(
            static function ($stop): void {
                $stop();
            },
            $eventDispatcher,
            $logger,
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

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->exactly(6))
            ->method('debug')
            ->willReturnMap([
                ['Worker starting'],
                ['Worker starting job run'],
                ['Worker finished job run ({ranTime}ms)'],
                ['Worker received stop signal'],
                ['Worker stopped'],
                ['Worker terminated'],
            ]);

        $worker = DefaultWorker::create(
            static function ($stop): void {
                $stop();
            },
            [],
            $logger,
            $eventDispatcher,
        );

        $worker->run(0);

        self::assertEquals(1, $listener->called);
    }

    public function testOptions(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->never())
            ->method('debug');

        $clock = new TestClock();

        $invokationCount = $this->exactly(4);
        $invokationParameters = [
            [new StopWorkerOnSignalListener(logger: $logger)],
            [new StopWorkerOnIterationLimitListener(10, $logger)],
            [new StopWorkerOnMemoryLimitListener(Bytes::parseFromString('10KB'), $logger)],
            [new StopWorkerOnTimeLimitListener(20, $logger, $clock)],
        ];

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($invokationCount)
            ->method('addSubscriber')
            ->willReturnCallback(function (...$parameters) use ($invokationCount, $invokationParameters): void {
                $this->assertEquals($invokationParameters[$invokationCount->numberOfInvocations() - 1], $parameters);
            });

        DefaultWorker::create(
            static function ($stop): void {
                $stop();
            },
            [
                'runLimit' => 10,
                'memoryLimit' => '10KB',
                'timeLimit' => 20,
            ],
            $logger,
            $eventDispatcher,
            $clock,
        );
    }

    public function testRunWorkerSleeping(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->exactly(9))
            ->method('debug')
            ->willReturnCallback(
                new ReturnCallback([
                    [['Worker starting', []]],
                    [['Worker starting job run', []]],
                    [['Worker finished job run ({ranTime}ms)', ['ranTime' => 10]]],
                    [['Worker sleep for {sleepTimer}ms', ['sleepTimer' => 190]]],
                    [['Worker starting job run', []]],
                    [['Worker finished job run ({ranTime}ms)', ['ranTime' => 10]]],
                    [['Worker received stop signal', []]],
                    [['Worker stopped', []]],
                    [['Worker terminated', []]],
                ]),
            );

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnIterationLimitListener(2));

        $worker = new DefaultWorker(
            static fn () => null,
            $eventDispatcher,
            $logger,
            new TestClock(tick: 10),
        );

        $worker->run(200);
    }

    public function testRunWorkerNotSleeping(): void
    {
        $invokationCount = $this->exactly(8);
        $invokationParameters = [
            ['Worker starting', []],
            ['Worker starting job run', []],
            ['Worker finished job run ({ranTime}ms)', ['ranTime' => 10]],
            ['Worker starting job run', []],
            ['Worker finished job run ({ranTime}ms)', ['ranTime' => 10]],
            ['Worker received stop signal', []],
            ['Worker stopped', []],
            ['Worker terminated', []],
        ];

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($invokationCount)
            ->method('debug')
            ->willReturnCallback(function (...$parameters) use ($invokationCount, $invokationParameters): void {
                $this->assertSame($invokationParameters[$invokationCount->numberOfInvocations() - 1], $parameters);
            });

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnIterationLimitListener(2));

        $worker = new DefaultWorker(
            static fn () => null,
            $eventDispatcher,
            $logger,
            new TestClock(tick: 10),
        );

        $worker->run(5);
    }

    public function testRunWorkerSkipsSleepWhenJobDidWork(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->exactly(11))
            ->method('debug')
            ->willReturnCallback(
                new ReturnCallback([
                    [['Worker starting', []]],
                    [['Worker starting job run', []]],
                    [['Worker finished job run ({ranTime}ms)', ['ranTime' => 10]]],
                    [['Worker starting job run', []]],
                    [['Worker finished job run ({ranTime}ms)', ['ranTime' => 10]]],
                    [['Worker sleep for {sleepTimer}ms', ['sleepTimer' => 190]]],
                    [['Worker starting job run', []]],
                    [['Worker finished job run ({ranTime}ms)', ['ranTime' => 10]]],
                    [['Worker received stop signal', []]],
                    [['Worker stopped', []]],
                    [['Worker terminated', []]],
                ]),
            );

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnIterationLimitListener(3));

        $jobResults = [true, false, true];

        $worker = new DefaultWorker(
            static function () use (&$jobResults): bool {
                return (bool)array_shift($jobResults);
            },
            $eventDispatcher,
            $logger,
            new TestClock(tick: 10),
        );

        $worker->run(200);
    }

    public function testDefaultCreate(): void
    {
        $calls = 0;
        $worker = DefaultWorker::create(
            static function ($stop) use (&$calls): void {
                $calls++;
                $stop();
            },
        );
        $worker->run(5);

        self::assertSame(1, $calls);
    }

    public function testRunWorkerTwice(): void
    {
        $calls = 0;

        $worker = new DefaultWorker(
            static function (callable $stop) use (&$calls): void {
                $calls++;
                $stop();
            },
            new EventDispatcher(),
        );

        $worker->run(0);
        $worker->run(0);

        self::assertSame(2, $calls);
    }

    public function testStopBeforeRun(): void
    {
        $calls = 0;

        $worker = new DefaultWorker(
            static function () use (&$calls): void {
                $calls++;
            },
            new EventDispatcher(),
        );

        $worker->stop();
        $worker->run(0);

        self::assertSame(0, $calls);
    }

    public function testStoppedEventWithoutException(): void
    {
        $stoppedEvent = null;

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            WorkerStoppedEvent::class,
            static function (WorkerStoppedEvent $event) use (&$stoppedEvent): void {
                $stoppedEvent = $event;
            },
        );

        $worker = new DefaultWorker(
            static function (callable $stop): void {
                $stop();
            },
            $eventDispatcher,
        );

        $worker->run(0);

        self::assertInstanceOf(WorkerStoppedEvent::class, $stoppedEvent);
        self::assertSame($worker, $stoppedEvent->worker);
        self::assertNull($stoppedEvent->exception);
    }

    public function testJobExceptionDispatchesStoppedEvent(): void
    {
        $exception = new RuntimeException('job failed');
        $stoppedEvent = null;

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            WorkerStoppedEvent::class,
            static function (WorkerStoppedEvent $event) use (&$stoppedEvent): void {
                $stoppedEvent = $event;
            },
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->exactly(4))
            ->method('debug')
            ->willReturnCallback(
                new ReturnCallback([
                    [['Worker starting', []]],
                    [['Worker starting job run', []]],
                    [['Worker stopped', []]],
                    [['Worker terminated', []]],
                ]),
            );

        $worker = new DefaultWorker(
            static function () use ($exception): void {
                throw $exception;
            },
            $eventDispatcher,
            $logger,
        );

        try {
            $worker->run(0);
            self::fail('Expected exception was not thrown');
        } catch (RuntimeException $e) {
            self::assertSame($exception, $e);
        }

        self::assertInstanceOf(WorkerStoppedEvent::class, $stoppedEvent);
        self::assertSame($exception, $stoppedEvent->exception);
    }
}
