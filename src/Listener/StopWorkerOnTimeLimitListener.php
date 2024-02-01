<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Listener;

use Patchlevel\Worker\Event\WorkerRunningEvent;
use Patchlevel\Worker\Event\WorkerStartedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function time;

final class StopWorkerOnTimeLimitListener implements EventSubscriberInterface
{
    private float $endTime = 0;

    /** @param positive-int $timeLimit in seconds */
    public function __construct(
        private readonly int $timeLimit,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function onWorkerStarted(): void
    {
        $this->endTime = time() + $this->timeLimit;
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if ($this->endTime >= time()) {
            return;
        }

        $event->worker->stop();
        $this->logger?->info(
            'Worker stopped due to time limit of {timeLimit}s exceeded',
            ['timeLimit' => $this->timeLimit],
        );
    }

    /** @return array<class-string, string> */
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerStartedEvent::class => 'onWorkerStarted',
            WorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }
}
