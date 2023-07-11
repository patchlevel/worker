<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Event;

use Patchlevel\Worker\Worker;

final class WorkerRunningEvent
{
    public function __construct(
        public readonly Worker $worker,
    ) {
    }
}
