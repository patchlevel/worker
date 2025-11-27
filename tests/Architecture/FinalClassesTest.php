<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class FinalClassesTest
{
    public function testFinalClasses(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::AllOf(
                    Selector::inNamespace('Patchlevel\Worker'),
                    Selector::NOT(Selector::isAbstract()),
                    Selector::NOT(Selector::isInterface()),
                ),
            )
            ->shouldBeFinal();
    }
}
