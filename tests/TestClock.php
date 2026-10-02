<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Tests;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

use function intdiv;
use function sprintf;

final class TestClock implements ClockInterface
{
    private int $milliseconds = 1_767_225_600_000;

    /** @param int $tick milliseconds the clock moves forward on every now() call */
    public function __construct(
        private readonly int $tick = 0,
    ) {
    }

    public function now(): DateTimeImmutable
    {
        $now = new DateTimeImmutable(sprintf('@%d.%03d', intdiv($this->milliseconds, 1000), $this->milliseconds % 1000));

        $this->milliseconds += $this->tick;

        return $now;
    }

    public function advance(int $milliseconds): void
    {
        $this->milliseconds += $milliseconds;
    }
}
