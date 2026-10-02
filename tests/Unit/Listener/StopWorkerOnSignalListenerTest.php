<?php

declare(strict_types=1);

namespace Patchlevel\Worker\Tests\Unit\Listener;

use Patchlevel\Worker\DefaultWorker;
use Patchlevel\Worker\Listener\StopWorkerOnSignalListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function getmypid;
use function pcntl_signal;
use function posix_kill;

use const SIG_DFL;
use const SIGINT;
use const SIGTERM;
use const SIGUSR1;

#[CoversClass(StopWorkerOnSignalListener::class)]
#[RequiresFunction('pcntl_signal')]
#[RequiresFunction('posix_kill')]
final class StopWorkerOnSignalListenerTest extends TestCase
{
    protected function tearDown(): void
    {
        pcntl_signal(SIGTERM, SIG_DFL);
        pcntl_signal(SIGINT, SIG_DFL);
        pcntl_signal(SIGUSR1, SIG_DFL);
    }

    public function testStopOnSigtermByDefault(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('info')
            ->with('Worker received signal {signal}', ['signal' => SIGTERM]);

        $calls = $this->runWorkerAndSendSignal(new StopWorkerOnSignalListener(logger: $logger), SIGTERM);

        self::assertSame(1, $calls);
    }

    public function testStopOnSigintByDefault(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('info')
            ->with('Worker received signal {signal}', ['signal' => SIGINT]);

        $calls = $this->runWorkerAndSendSignal(new StopWorkerOnSignalListener(logger: $logger), SIGINT);

        self::assertSame(1, $calls);
    }

    public function testStopOnCustomSignal(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('info')
            ->with('Worker received signal {signal}', ['signal' => SIGUSR1]);

        $calls = $this->runWorkerAndSendSignal(new StopWorkerOnSignalListener([SIGUSR1], $logger), SIGUSR1);

        self::assertSame(1, $calls);
    }

    /**
     * Sends the signal during the first job run. The job stops the worker
     * itself after 5 runs, so more than 1 run means the signal was ignored.
     */
    private function runWorkerAndSendSignal(StopWorkerOnSignalListener $listener, int $signal): int
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber($listener);

        $calls = 0;

        $worker = new DefaultWorker(
            static function (callable $stop) use (&$calls, $signal): void {
                $calls++;

                if ($calls === 1) {
                    posix_kill((int)getmypid(), $signal);
                }

                if ($calls < 5) {
                    return;
                }

                $stop();
            },
            $eventDispatcher,
        );

        $worker->run(0);

        return $calls;
    }
}
