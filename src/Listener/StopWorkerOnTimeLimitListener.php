<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Listener;

use DateInterval;
use DateTimeImmutable;
use Patchlevel\Worker\Event\WorkerRunningEvent;
use Patchlevel\Worker\Event\WorkerStartedEvent;
use Patchlevel\Worker\SystemClock;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class StopWorkerOnTimeLimitListener implements EventSubscriberInterface
{
    private DateTimeImmutable|null $endTime = null;

    private readonly ClockInterface $clock;

    /** @param positive-int $timeLimit in seconds */
    public function __construct(
        private readonly int $timeLimit,
        private readonly LoggerInterface|null $logger = null,
        ClockInterface|null $clock = null,
    ) {
        $this->clock = $clock ?? new SystemClock();
    }

    public function onWorkerStarted(): void
    {
        $this->endTime = $this->clock->now()->add(new DateInterval('PT' . $this->timeLimit . 'S'));
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if ($this->endTime !== null && $this->clock->now() < $this->endTime) {
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
