<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Tests\Unit;

use Generator;
use Patchlevel\Worker\Bytes;
use Patchlevel\Worker\InvalidFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Bytes::class)]
final class BytesTest extends TestCase
{
    #[DataProvider('invalidFormatProvider')]
    public function testParseInvalidUnit(string $bytes): void
    {
        $this->expectException(InvalidFormat::class);

        Bytes::parseFromString($bytes);
    }

    /** @return Generator<array-key, array{string}> */
    public static function invalidFormatProvider(): Generator
    {
        yield ['-5GB'];
        yield ['505Foo'];
        yield ['50Kb50'];
    }

    #[DataProvider('validParseDataProvider')]
    public function testValidParse(string $string, int $expectedBytes, string $expectedFormatted): void
    {
        $bytes = Bytes::parseFromString($string);

        self::assertSame($expectedBytes, $bytes->value());
        self::assertSame($expectedFormatted, $bytes->formatted());
    }

    /** @return Generator<array-key, array{string, int, string}> */
    public static function validParseDataProvider(): Generator
    {
        yield ['50', 50, '50 B'];
        yield ['50B', 50, '50 B'];
        yield ['50b', 50, '50 B'];
        yield ['50KB', 51_200, '50.0 KiB'];
        yield ['50kb', 51_200, '50.0 KiB'];
        yield ['50Kb', 51_200, '50.0 KiB'];
        yield ['50MB', 52_428_800, '50.0 MiB'];
        yield ['50Mb', 52_428_800, '50.0 MiB'];
        yield ['50mb', 52_428_800, '50.0 MiB'];
        yield ['50GB', 53_687_091_200, '50.0 GiB'];
        yield ['50Gb', 53_687_091_200, '50.0 GiB'];
        yield ['50gb', 53_687_091_200, '50.0 GiB'];

        yield ['1024b', 1024, '1.0 KiB'];
        yield ['1024Kb', 1_048_576, '1.0 MiB'];
        yield ['1024Mb', 1_073_741_824, '1.0 GiB'];
    }
}
