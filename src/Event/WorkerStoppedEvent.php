<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Event;

use Patchlevel\Worker\Worker;
use Throwable;

final class WorkerStoppedEvent
{
    public function __construct(
        public readonly Worker $worker,
        public readonly Throwable|null $exception = null,
    ) {
    }
}
