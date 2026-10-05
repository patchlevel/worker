<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Listener;

use Patchlevel\Worker\Event\WorkerRunningEvent;
use Patchlevel\Worker\Event\WorkerStartedEvent;
use Patchlevel\Worker\Event\WorkerStoppedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function is_file;
use function touch;
use function unlink;

final class HeartbeatListener implements EventSubscriberInterface
{
    /** @param string $file touched on start and after every iteration, removed when the worker stops */
    public function __construct(
        private readonly string $file,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function onWorkerStarted(): void
    {
        $this->beat();
    }

    public function onWorkerRunning(): void
    {
        $this->beat();
    }

    public function onWorkerStopped(): void
    {
        if (!is_file($this->file)) {
            return;
        }

        unlink($this->file);
    }

    private function beat(): void
    {
        if (@touch($this->file)) {
            return;
        }

        $this->logger?->warning('Worker could not update heartbeat file {file}', ['file' => $this->file]);
    }

    /** @return array<class-string, string> */
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerStartedEvent::class => 'onWorkerStarted',
            WorkerRunningEvent::class => 'onWorkerRunning',
            WorkerStoppedEvent::class => 'onWorkerStopped',
        ];
    }
}
