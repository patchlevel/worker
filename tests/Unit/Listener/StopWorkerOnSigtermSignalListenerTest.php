<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Tests\Unit\Listener;

use Patchlevel\Worker\DefaultWorker;
use Patchlevel\Worker\Listener\StopWorkerOnSigtermSignalListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function getmypid;
use function pcntl_signal;
use function posix_kill;

use const SIG_DFL;
use const SIGTERM;

#[CoversClass(StopWorkerOnSigtermSignalListener::class)]
#[RequiresFunction('pcntl_signal')]
#[RequiresFunction('posix_kill')]
final class StopWorkerOnSigtermSignalListenerTest extends TestCase
{
    protected function tearDown(): void
    {
        pcntl_signal(SIGTERM, SIG_DFL);
    }

    public function testStopOnSigterm(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('info')
            ->with('Received SIGTERM signal.');

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnSigtermSignalListener($logger));

        $calls = 0;

        $worker = new DefaultWorker(
            static function (callable $stop) use (&$calls): void {
                $calls++;

                if ($calls === 1) {
                    posix_kill((int)getmypid(), SIGTERM);
                }

                if ($calls < 5) {
                    return;
                }

                $stop();
            },
            $eventDispatcher,
        );

        $worker->run(0);

        self::assertSame(1, $calls);
    }
}
