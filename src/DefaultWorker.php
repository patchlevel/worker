<?php

declare(strict_types=1);

namespace Patchlevel\Worker;

use Closure;
use Patchlevel\Worker\Event\WorkerRunningEvent;
use Patchlevel\Worker\Event\WorkerStartedEvent;
use Patchlevel\Worker\Event\WorkerStoppedEvent;
use Patchlevel\Worker\Listener\HeartbeatListener;
use Patchlevel\Worker\Listener\StopWorkerOnIterationLimitListener;
use Patchlevel\Worker\Listener\StopWorkerOnMemoryLimitListener;
use Patchlevel\Worker\Listener\StopWorkerOnRestartSignalListener;
use Patchlevel\Worker\Listener\StopWorkerOnSignalListener;
use Patchlevel\Worker\Listener\StopWorkerOnTimeLimitListener;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;

use function max;
use function usleep;

final class DefaultWorker implements Worker
{
    private bool $shouldStop = false;

    private readonly ClockInterface $clock;

    /** @param Closure(Closure):(bool|void) $job return true if the job did work to skip the sleep */
    public function __construct(
        private readonly Closure $job,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface|null $logger = null,
        ClockInterface|null $clock = null,
    ) {
        $this->clock = $clock ?? new SystemClock();
    }

    /** @param positive-int|0 $sleepTimer in milliseconds */
    public function run(int $sleepTimer = 1000): void
    {
        $this->logger?->debug('Worker starting');

        $this->eventDispatcher->dispatch(new WorkerStartedEvent($this));

        $exception = null;

        try {
            while (!$this->shouldStop) {
                $this->logger?->debug('Worker starting job run');

                $startTime = $this->milliseconds();

                $didWork = ($this->job)($this->stop(...)) === true;

                $endTime = $this->milliseconds();
                $ranTime = $endTime - $startTime;

                $this->logger?->debug('Worker finished job run ({ranTime}ms)', ['ranTime' => $ranTime]);

                $this->eventDispatcher->dispatch(new WorkerRunningEvent($this));

                if ($this->shouldStop) {
                    break;
                }

                if ($didWork) {
                    continue;
                }

                $sleepFor = max($sleepTimer - $ranTime, 0);

                if ($sleepFor <= 0) {
                    continue;
                }

                $this->logger?->debug('Worker sleep for {sleepTimer}ms', ['sleepTimer' => $sleepFor]);
                usleep($sleepFor * 1000);
            }
        } catch (Throwable $exception) {
            throw $exception;
        } finally {
            $this->shouldStop = false;

            $this->logger?->debug('Worker stopped');

            $this->eventDispatcher->dispatch(new WorkerStoppedEvent($this, $exception));

            $this->logger?->debug('Worker terminated');
        }
    }

    public function stop(): void
    {
        $this->logger?->debug('Worker received stop signal');
        $this->shouldStop = true;
    }

    /**
     * @param Closure(Closure):(bool|void)                                                                                                                                          $job
     * @param array{runLimit?: (positive-int|null), memoryLimit?: (string|null), timeLimit?: (positive-int|null), restartSignalFile?: (string|null), heartbeatFile?: (string|null)} $options
     */
    public static function create(
        Closure $job,
        array $options = [],
        LoggerInterface $logger = new NullLogger(),
        EventDispatcherInterface|null $eventDispatcher = null,
        ClockInterface|null $clock = null,
    ): self {
        if ($eventDispatcher === null) {
            $eventDispatcher = new EventDispatcher();
        }

        $eventDispatcher->addSubscriber(new StopWorkerOnSignalListener(logger: $logger));

        if (isset($options['runLimit'])) {
            $eventDispatcher->addSubscriber(
                new StopWorkerOnIterationLimitListener($options['runLimit'], $logger),
            );
        }

        if (isset($options['memoryLimit'])) {
            $eventDispatcher->addSubscriber(
                new StopWorkerOnMemoryLimitListener(Bytes::parseFromString($options['memoryLimit']), $logger),
            );
        }

        if (isset($options['timeLimit'])) {
            $eventDispatcher->addSubscriber(
                new StopWorkerOnTimeLimitListener($options['timeLimit'], $logger, $clock),
            );
        }

        if (isset($options['restartSignalFile'])) {
            $eventDispatcher->addSubscriber(
                new StopWorkerOnRestartSignalListener($options['restartSignalFile'], $logger, $clock),
            );
        }

        if (isset($options['heartbeatFile'])) {
            $eventDispatcher->addSubscriber(
                new HeartbeatListener($options['heartbeatFile'], $logger),
            );
        }

        return new self(
            $job,
            $eventDispatcher,
            $logger,
            $clock,
        );
    }

    private function milliseconds(): int
    {
        return (int)$this->clock->now()->format('Uv');
    }
}
