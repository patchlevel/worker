<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Event;

use Patchlevel\Worker\Worker;

final class WorkerStartedEvent
{
    public function __construct(
        public readonly Worker $worker,
    ) {
    }
}
