[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fpatchlevel%2Fhydrator%2F2.0.x)](https://dashboard.stryker-mutator.io/reports/github.com/patchlevel/hydrator/2.0.x)
[![Type Coverage](https://shepherd.dev/github/patchlevel/hydrator/coverage.svg)](https://shepherd.dev/github/patchlevel/hydrator)
[![Latest Stable Version](https://poser.pugx.org/patchlevel/hydrator/v)](//packagist.org/packages/patchlevel/hydrator)
[![License](https://poser.pugx.org/patchlevel/hydrator/license)](//packagist.org/packages/patchlevel/hydrator)

# Worker

Gives the opportunity to build a stable worker that terminates properly when limits are exceeded.
It has now been outsourced by the [event-sourcing](https://github.com/patchlevel/event-sourcing) library as a separate library.

## Installation

```bash
composer require patchlevel/worker
```

## Example

```php
<?php

declare(strict_types=1);

namespace App\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'app:worker',
    'do stuff'
)]
final class WorkerCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption(
                'run-limit',
                null,
                InputOption::VALUE_OPTIONAL,
                'The maximum number of runs this command should execute',
                1
            )
            ->addOption(
                'memory-limit',
                null,
                InputOption::VALUE_REQUIRED,
                'How much memory consumption should the worker be terminated (500MB, 1GB, etc.)'
            )
            ->addOption(
                'time-limit',
                null,
                InputOption::VALUE_REQUIRED,
                'What is the maximum time the worker can run in seconds'
            )
            ->addOption(
                'sleep',
                null,
                InputOption::VALUE_REQUIRED,
                'How much time should elapse before the next job is executed in milliseconds',
                1000
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logger = new ConsoleLogger($output);
        
        $worker = DefaultWorker::create(
            function (): void {
                // do something
            },
            [
                'runLimit' => $input->getOption('run-limit'),
                'memoryLimit' => $input->getOption('memory-limit'),
                'timeLimit' => $input->getOption('time-limit'),
            ],
            $logger
        );

        $worker->run($input->getOption('sleep') ?: null);

        return 0;
    }
}
```