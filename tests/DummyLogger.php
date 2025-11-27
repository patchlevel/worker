<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Tests;

use Psr\Log\AbstractLogger;
use Stringable;

final class DummyLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string|Stringable, context: array<array-key, mixed>}> */
    public array $entries = [];

    /** @param array<array-key, mixed> $context */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->entries[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}
