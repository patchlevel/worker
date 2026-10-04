<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Listener;

use Patchlevel\Worker\Event\WorkerRunningEvent;
use Patchlevel\Worker\Event\WorkerStartedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function clearstatcache;
use function filemtime;
use function is_file;
use function time;

final class StopWorkerOnRestartSignalListener implements EventSubscriberInterface
{
    private int $startTime = 0;

    /** @param string $file the worker stops if this file is touched after the worker has started */
    public function __construct(
        private readonly string $file,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function onWorkerStarted(): void
    {
        $this->startTime = time();
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        clearstatcache(true, $this->file);

        if (!is_file($this->file)) {
            return;
        }

        $modifiedTime = filemtime($this->file);

        if ($modifiedTime === false || $modifiedTime <= $this->startTime) {
            return;
        }

        $this->logger?->info(
            'Worker stopped due to restart signal from {file}',
            ['file' => $this->file],
        );

        $event->worker->stop();
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
