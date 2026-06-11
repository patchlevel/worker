[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fpatchlevel%2Fworker%2F1.5.x)](https://dashboard.stryker-mutator.io/reports/github.com/patchlevel/worker/1.5.x)
[![Latest Stable Version](https://poser.pugx.org/patchlevel/worker/v)](//packagist.org/packages/patchlevel/worker)
[![License](https://poser.pugx.org/patchlevel/worker/license)](//packagist.org/packages/patchlevel/worker)

# Worker

A small library to build stable, long-running workers that terminate properly when limits are exceeded.
A worker executes a job in a loop and stops gracefully when a run, memory or time limit is reached or
a `SIGTERM` signal is received. This makes it a good fit for daemonized console commands managed by
supervisor, systemd, Docker or Kubernetes, where you want processes to restart cleanly instead of being killed mid-job.

It was originally part of the [event-sourcing](https://github.com/patchlevel/event-sourcing) library
and has been extracted as a standalone library.

## Features

* Graceful shutdown: the worker always finishes the current job before stopping
* Stop the worker after a maximum number of iterations
* Stop the worker when a memory limit is exceeded
* Stop the worker after a time limit
* Stop the worker on `SIGTERM` (e.g. sent by supervisor, Docker or Kubernetes)
* Configurable sleep time between iterations
* PSR-3 logging and Symfony event dispatcher integration

## Requirements

* PHP 8.2+
* [symfony/event-dispatcher](https://github.com/symfony/event-dispatcher)
* The [pcntl](https://www.php.net/manual/en/book.pcntl.php) extension (optional, needed for `SIGTERM` handling)

## Installation

```bash
composer require patchlevel/worker
```

## Usage

The easiest way to create a worker is the `DefaultWorker::create` factory method.
It takes the job as a closure, an array of options and optionally a PSR-3 logger:

```php
use Patchlevel\Worker\DefaultWorker;

$worker = DefaultWorker::create(
    function (Closure $stop): void {
        // do something, e.g. consume a message from a queue

        if (/* some condition */) {
            $stop(); // stop the worker from inside the job
        }
    },
    [
        'runLimit' => 100,      // stop after 100 iterations
        'memoryLimit' => '512MB', // stop if memory usage exceeds 512MB
        'timeLimit' => 3600,    // stop after one hour (in seconds)
    ],
);

$worker->run(1000); // sleep up to 1000ms between iterations
```

The job receives a `$stop` closure as its first argument. Calling it stops the worker
after the current iteration — useful when the job itself detects that there is nothing left to do.

### Options

All options are optional. If an option is not set, the corresponding limit is not enforced.

| Option        | Type           | Description                                                                  |
|---------------|----------------|------------------------------------------------------------------------------|
| `runLimit`    | `positive-int` | Stop the worker after this number of job runs                                |
| `memoryLimit` | `string`       | Stop the worker if memory usage exceeds this limit (e.g. `128MB`, `1GB`)     |
| `timeLimit`   | `positive-int` | Stop the worker after this number of seconds                                 |

The `memoryLimit` string consists of a number and a unit. Allowed units are `B`, `KB`, `MB` and `GB`
(case-insensitive). If no unit is given, bytes are assumed.

> [!NOTE]
> Limits are checked *after* each job run. The current job is always finished before the worker stops,
> so the actual runtime or memory usage may exceed the configured limit slightly.

### Sleep timer

The `run` method accepts a sleep timer in milliseconds (default: `1000`):

```php
$worker->run(500);
```

The time the job took is subtracted from the sleep timer. If a job run takes longer than
the sleep timer, the next run starts immediately. Pass `0` to disable sleeping entirely.

### Stopping the worker

There are several ways the worker can be stopped:

* Calling the `$stop` closure inside the job
* Calling `$worker->stop()` from outside (e.g. from an event listener)
* Reaching one of the configured limits (`runLimit`, `memoryLimit`, `timeLimit`)
* Receiving a `SIGTERM` signal (requires the `pcntl` extension)

In all cases the worker finishes the current job and then exits the loop gracefully.

### Logging

`DefaultWorker::create` accepts any PSR-3 logger as the third argument.
The worker logs each iteration at `debug` level and the reason for stopping at `info` level:

```php
use Patchlevel\Worker\DefaultWorker;
use Symfony\Component\Console\Logger\ConsoleLogger;

$logger = new ConsoleLogger($output);

$worker = DefaultWorker::create(
    function (): void {
        // do something
    },
    ['runLimit' => 100],
    $logger,
);
```

## Events

The worker dispatches the following events via the Symfony event dispatcher.
Each event exposes the worker instance as a public readonly property `$worker`,
so listeners can call `$event->worker->stop()`.

| Event                | Dispatched                                                |
|----------------------|------------------------------------------------------------|
| `WorkerStartedEvent` | Once, before the first job run                             |
| `WorkerRunningEvent` | After every job run                                        |
| `WorkerStoppedEvent` | Once, after the worker loop has finished                   |

All events live in the `Patchlevel\Worker\Event` namespace.

### Custom event listeners

You can pass your own event dispatcher as the fourth argument of `DefaultWorker::create`
and register custom listeners or subscribers on it:

```php
use Patchlevel\Worker\DefaultWorker;
use Patchlevel\Worker\Event\WorkerRunningEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

$eventDispatcher = new EventDispatcher();
$eventDispatcher->addListener(WorkerRunningEvent::class, function (WorkerRunningEvent $event): void {
    if (/* some condition, e.g. a new deployment was detected */) {
        $event->worker->stop();
    }
});

$worker = DefaultWorker::create(
    function (): void {
        // do something
    },
    [],
    eventDispatcher: $eventDispatcher,
);
```

## Listeners

Internally, the limits are implemented as event subscribers in the `Patchlevel\Worker\Listener` namespace.
`DefaultWorker::create` registers them automatically based on the given options,
but you can also use them directly with your own event dispatcher:

| Listener                              | Registered by                | Description                                       |
|---------------------------------------|------------------------------|---------------------------------------------------|
| `StopWorkerOnIterationLimitListener`  | `runLimit` option            | Stops the worker after N job runs                 |
| `StopWorkerOnMemoryLimitListener`     | `memoryLimit` option         | Stops the worker when memory usage exceeds limit  |
| `StopWorkerOnTimeLimitListener`       | `timeLimit` option           | Stops the worker after N seconds                  |
| `StopWorkerOnSigtermSignalListener`   | always (if pcntl is loaded)  | Stops the worker on `SIGTERM`                     |

## Full example: Symfony console command

A typical use case is a long-running console command where the limits are configurable via CLI options:

```php
<?php

declare(strict_types=1);

namespace App\Console\Command;

use Patchlevel\Worker\DefaultWorker;
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
            function ($stop): void {
                // do something

                if (/* some condition */) {
                    $stop();
                }
            },
            [
                'runLimit' => $input->getOption('run-limit'),
                'memoryLimit' => $input->getOption('memory-limit'),
                'timeLimit' => $input->getOption('time-limit'),
            ],
            $logger
        );

        $worker->run((int)$input->getOption('sleep'));

        return 0;
    }
}
```

The command can then be run like this:

```bash
bin/console app:worker --run-limit=100 --memory-limit=512MB --time-limit=3600 --sleep=1000
```
