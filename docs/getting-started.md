# Getting Started

The easiest way to create a worker is the `DefaultWorker::create` factory.
It takes the job to execute, an array of limit options and an optional PSR-3 logger:

```php
use Patchlevel\Worker\DefaultWorker;

$worker = DefaultWorker::create(
    function (callable $stop): void {
        // do a unit of work

        if (/* nothing left to do */) {
            $stop();
        }
    },
    [
        'runLimit' => 100,
        'memoryLimit' => '512MB',
        'timeLimit' => 3600,
    ],
    $logger, // optional, any PSR-3 logger
);

$worker->run();
```

The job is executed in a loop until the worker is stopped.
The job receives a `$stop` callback: calling it tells the worker to exit the loop
after the current iteration has finished. The worker never aborts a running job —
stopping always happens *between* iterations, so your job is never interrupted halfway.

## Limits

All options are optional. Without limits the worker runs until it is stopped
via `$stop()`, `$worker->stop()` or a SIGTERM signal.

| Option        | Type     | Description                                                                                                                     |
|---------------|----------|---------------------------------------------------------------------------------------------------------------------------------|
| `runLimit`    | `int`    | Stop after this number of iterations.                                                                                           |
| `memoryLimit` | `string` | Stop when memory usage exceeds this value, e.g. `128MB`. Supported units: `B`, `KB`, `MB`, `GB` (case-insensitive, 1024-based). |
| `timeLimit`   | `int`    | Stop after this number of seconds.                                                                                              |

Limits are checked after each iteration. When a limit is exceeded, the worker logs the reason and stops gracefully.

:::warning
An invalid `memoryLimit` string throws a `Patchlevel\Worker\InvalidFormat` exception.
:::

:::note
Internally every limit is implemented as an event listener.
You can add your own stop conditions the same way, see [events & listeners](events.md).
:::

## Graceful shutdown on SIGTERM

If the `pcntl` extension is available, the worker automatically registers a SIGTERM handler.
When the process receives SIGTERM (e.g. from `docker stop`, a Kubernetes pod shutdown or supervisor),
the worker finishes the current iteration and then exits cleanly.

This makes the worker a good fit for process managers that send SIGTERM and restart the process,
e.g. to roll out a new version or to keep long-running processes fresh.

:::warning
Without `ext-pcntl` this feature is not available.
:::

## Sleep

`run()` takes a sleep timer in milliseconds (default: `1000`):

```php
$worker->run(500); // aim for one iteration every 500ms
```

The job's own run time is subtracted from the sleep: if the job took 300ms and the sleep timer
is 500ms, the worker only sleeps 200ms. If the job took longer than the sleep timer,
the next iteration starts immediately. Pass `0` to disable sleeping entirely.

## Logging

The worker logs its lifecycle (start, iteration timings, sleep, stop reason) to the given PSR-3 logger.
Iteration details use the `debug` level; stop reasons (limit exceeded, SIGTERM received) use `info`.

With the `ConsoleLogger` from the [Symfony command example](integration.md#symfony), run the command with `-v`
to see stop reasons or `-vvv` to see everything.
