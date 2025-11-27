<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Tests\Unit;

use Patchlevel\Worker\Bytes;
use Patchlevel\Worker\DefaultWorker;
use Patchlevel\Worker\Event\WorkerRunningEvent;
use Patchlevel\Worker\Event\WorkerStartedEvent;
use Patchlevel\Worker\Listener\StopWorkerOnIterationLimitListener;
use Patchlevel\Worker\Listener\StopWorkerOnMemoryLimitListener;
use Patchlevel\Worker\Listener\StopWorkerOnSigtermSignalListener;
use Patchlevel\Worker\Listener\StopWorkerOnTimeLimitListener;
use Patchlevel\Worker\Tests\ReturnCallback;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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

        $invokationCount = $this->exactly(4);
        $invokationParameters = [
            [new StopWorkerOnSigtermSignalListener($logger)],
            [new StopWorkerOnIterationLimitListener(10, $logger)],
            [new StopWorkerOnMemoryLimitListener(Bytes::parseFromString('10KB'), $logger)],
            [new StopWorkerOnTimeLimitListener(20, $logger)],
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

        $calls = 0;

        $worker = new DefaultWorker(
            static fn () => null,
            $eventDispatcher,
            $logger,
        );

        (new ReflectionClass($worker))
            ->getProperty('timeMeasure')
            ->setValue(
                $worker,
                static function () use (&$calls): int {
                    $calls++;

                    return $calls * 10;
                },
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

        $calls = 0;

        $worker = new DefaultWorker(
            static fn () => null,
            $eventDispatcher,
            $logger,
        );

        (new ReflectionClass($worker))
            ->getProperty('timeMeasure')
            ->setValue(
                $worker,
                static function () use (&$calls): int {
                    $calls++;

                    return $calls * 10;
                },
            );

        $worker->run(5);
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
}
