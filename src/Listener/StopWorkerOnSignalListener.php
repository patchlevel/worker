<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Listener;

use Patchlevel\Worker\Event\WorkerStartedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function function_exists;
use function pcntl_async_signals;
use function pcntl_signal;

use const SIGINT;
use const SIGTERM;

final class StopWorkerOnSignalListener implements EventSubscriberInterface
{
    /** @param list<int>|null $signals defaults to SIGTERM and SIGINT */
    public function __construct(
        private readonly array|null $signals = null,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function onWorkerStarted(WorkerStartedEvent $event): void
    {
        pcntl_async_signals(true);

        foreach ($this->signals ?? [SIGTERM, SIGINT] as $signal) {
            pcntl_signal($signal, function (int $signal) use ($event): void {
                $this->logger?->info('Worker received signal {signal}', ['signal' => $signal]);
                $event->worker->stop();
            });
        }
    }

    /** @return array<class-string, string> */
    public static function getSubscribedEvents(): array
    {
        if (!function_exists('pcntl_signal')) {
            return [];
        }

        return [WorkerStartedEvent::class => 'onWorkerStarted'];
    }
}
