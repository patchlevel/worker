<?php

declare(strict_types=1);

namespace Patchlevel\Worker;

use Closure;
use Patchlevel\Worker\Event\WorkerRunningEvent;
use Patchlevel\Worker\Event\WorkerStartedEvent;
use Patchlevel\Worker\Event\WorkerStoppedEvent;
use Patchlevel\Worker\Listener\StopWorkerOnIterationLimitListener;
use Patchlevel\Worker\Listener\StopWorkerOnMemoryLimitListener;
use Patchlevel\Worker\Listener\StopWorkerOnSigtermSignalListener;
use Patchlevel\Worker\Listener\StopWorkerOnTimeLimitListener;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use function max;
use function microtime;
use function usleep;

final class DefaultWorker implements Worker
{
    private bool $shouldStop = false;

    public function __construct(
        private readonly Closure $job,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function run(int $sleepTimer = 1000): void
    {
        $this->logger?->debug('Worker starting');

        $this->eventDispatcher->dispatch(new WorkerStartedEvent($this));

        while (!$this->shouldStop) {
            $this->logger?->debug('Worker starting job run');

            $startTime = microtime(true);

            ($this->job)();

            $ranTime = (int)(microtime(true) - $startTime);

            $this->logger?->debug('Worker finished job run ({ranTime}ms)', ['ranTime' => $ranTime]);

            $this->eventDispatcher->dispatch(new WorkerRunningEvent($this));

            if ($this->shouldStop) {
                break;
            }

            $sleepFor = max($sleepTimer - $ranTime, 0);

            if ($sleepFor <= 0) {
                continue;
            }

            $this->logger?->debug('Worker sleep for {sleepTimer}ms', ['sleepTimer' => $sleepFor]);
            usleep($sleepFor * 1000);
        }

        $this->logger?->debug('Worker stopped');

        $this->eventDispatcher->dispatch(new WorkerStoppedEvent($this));

        $this->logger?->debug('Worker terminated');
    }

    public function stop(): void
    {
        $this->logger?->debug('Worker received stop signal');
        $this->shouldStop = true;
    }

    /** @param array{runLimit?: (positive-int|null), memoryLimit?: (string|null), timeLimit?: (positive-int|null)} $options */
    public function create(
        Closure $job,
        array $options,
        LoggerInterface $logger = new NullLogger(),
    ): self {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnSigtermSignalListener($logger));

        if ($options['runLimit'] ?? null) {
            $eventDispatcher->addSubscriber(
                new StopWorkerOnIterationLimitListener($options['runLimit'], $logger),
            );
        }

        if ($options['memoryLimit'] ?? null) {
            $eventDispatcher->addSubscriber(
                new StopWorkerOnMemoryLimitListener(Bytes::parseFromString($options['memoryLimit']), $logger),
            );
        }

        if ($options['timeLimit'] ?? null) {
            $eventDispatcher->addSubscriber(
                new StopWorkerOnTimeLimitListener($options['timeLimit'], $logger),
            );
        }

        return new self(
            $job,
            $eventDispatcher,
            $logger,
        );
    }
}
