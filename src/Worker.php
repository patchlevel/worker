<?php

declare(strict_types=1);

namespace Patchlevel\Worker;

interface Worker
{
    /** @param 0|positive-int $sleepTimer sleepTimer in milliseconds */
    public function run(int $sleepTimer = 1000): void;

    public function stop(): void;
}
