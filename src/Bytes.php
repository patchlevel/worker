<?php

declare(strict_types=1);

namespace Patchlevel\Worker;

use function array_key_exists;
use function is_int;
use function preg_match;
use function sprintf;
use function strtoupper;

final class Bytes
{
    private const SIZES = [
        'B' => 1,
        'K' => 1_024,
        'KB' => 1_024,
        'M' => 1_048_576,
        'MB' => 1_048_576,
        'G' => 1_073_741_824,
        'GB' => 1_073_741_824,
    ];

    private const UNIT_THRESHOLD = 1_024;

    public function __construct(
        private readonly int $bytes,
    ) {
    }

    public function value(): int
    {
        return $this->bytes;
    }

    public static function parseFromString(string $string): self
    {
        if (!preg_match('/^([0-9]+)([a-z]+)?$/i', $string, $matches)) {
            throw new InvalidFormat($string);
        }

        $unit = strtoupper($matches[2] ?? 'B');

        if (!array_key_exists($unit, self::SIZES)) {
            throw new InvalidFormat($string);
        }

        $bytes = $matches[1] * self::SIZES[$unit];

        // integer overflow results in a float
        if (!is_int($bytes)) {
            throw new InvalidFormat($string);
        }

        return new self($bytes);
    }

    public function formatted(): string
    {
        if ($this->bytes >= self::UNIT_THRESHOLD * self::UNIT_THRESHOLD * self::UNIT_THRESHOLD) {
            return sprintf('%.1f GiB', $this->bytes / self::UNIT_THRESHOLD / self::UNIT_THRESHOLD / self::UNIT_THRESHOLD);
        }

        if ($this->bytes >= self::UNIT_THRESHOLD * self::UNIT_THRESHOLD) {
            return sprintf('%.1f MiB', $this->bytes / self::UNIT_THRESHOLD / self::UNIT_THRESHOLD);
        }

        if ($this->bytes >= self::UNIT_THRESHOLD) {
            return sprintf('%.1f KiB', $this->bytes / self::UNIT_THRESHOLD);
        }

        return sprintf('%d B', $this->bytes);
    }
}
