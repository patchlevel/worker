# Getting Started

The easiest way to create a worker is the `DefaultWorker::create` factory.
It takes the job to execute, an array of limit options and an optional PSR-3 logger:

```php
use Patchlevel\Worker\DefaultWorker;

$worker = DefaultWorker::create(
    static function (callable $stop): void {
        // do a unit of work

        if (nothing_left_todo()) {
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

If the job throws an exception, the worker stops, dispatches the `WorkerStoppedEvent`
and rethrows the exception, so the process exits with an error.

## Limits

All options are optional. Without limits the worker runs until it is stopped
via `$stop()`, `$worker->stop()` or a SIGTERM/SIGINT signal.

| Option          | Type     | Description                                                                                                                                           |
|-----------------|----------|-------------------------------------------------------------------------------------------------------------------------------------------------------|
| `runLimit`      | `int`    | Stop after this number of iterations.                                                                                                                 |
| `memoryLimit`   | `string` | Stop when memory usage exceeds this value, e.g. `128MB` or `128M`. Supported units: `B`, `K`/`KB`, `M`/`MB`, `G`/`GB` (case-insensitive, 1024-based). |
| `timeLimit`     | `int`    | Stop after this number of seconds.                                                                                                                    |
| `heartbeatFile` | `string` | Touch this file on start and after every iteration, see [heartbeat](#heartbeat).                                                                      |

Limits are checked after each iteration. When a limit is exceeded, the worker logs the reason and stops gracefully.

:::warning
An invalid or too large `memoryLimit` string throws a `Patchlevel\Worker\InvalidFormat` exception.
:::

:::note
Internally every limit is implemented as an event listener.
You can add your own stop conditions the same way, see [events & listeners](events.md).
:::

## Graceful shutdown

If the `pcntl` extension is available, the worker automatically registers a handler for SIGTERM and SIGINT.
When the process receives SIGTERM (e.g. from `docker stop`, a Kubernetes pod shutdown or supervisor)
or SIGINT (e.g. pressing `Ctrl+C`), the worker finishes the current iteration and then exits cleanly.

This makes the worker a good fit for process managers that send SIGTERM and restart the process,
e.g. to roll out a new version or to keep long-running processes fresh.

:::warning
Without `ext-pcntl` this feature is not available.
:::

If you need to react to other signals, register the `StopWorkerOnSignalListener` with your own list of signals
on a custom event dispatcher, see [events & listeners](events.md).

## Sleep

`run()` takes a sleep timer in milliseconds (default: `1000`):

```php
$worker->run(500); // aim for one iteration every 500ms
```
The job's own run time is subtracted from the sleep: if the job took 300ms and the sleep timer
is 500ms, the worker only sleeps 200ms. If the job took longer than the sleep timer,
the next iteration starts immediately. Pass `0` to disable sleeping entirely.

### Skip the sleep when there is work

If the job returns `true`, the worker skips the sleep and starts the next iteration immediately.
This is useful for queue consumers: as long as there are messages, they are processed without a pause,
and the worker only sleeps once the queue is empty.

```php
use Patchlevel\Worker\DefaultWorker;

$worker = DefaultWorker::create(
    static function (callable $stop) use ($queue): bool {
        $message = $queue->pop();

        if ($message === null) {
            return false; // nothing to do, sleep
        }

        handle($message);

        return true; // there may be more, continue immediately
    },
);

$worker->run(1000);
```
Any other return value, including no return value at all, keeps the regular sleep behaviour.

## Heartbeat

A process manager only sees whether the worker process is running, not whether it is stuck in a job.
With the `heartbeatFile` option the worker touches a file when it starts and after every iteration,
and removes it when it stops. A liveness probe can then check how old the file is:

```php
use Patchlevel\Worker\DefaultWorker;

$worker = DefaultWorker::create(
    $job,
    ['heartbeatFile' => '/tmp/worker-heartbeat'],
    $logger,
);
```
For example as a Kubernetes liveness probe that restarts the pod if the file is older than 60 seconds:

```yaml
livenessProbe:
  exec:
    command:
      - sh
      - -c
      - test $(( $(date +%s) - $(stat -c %Y /tmp/worker-heartbeat) )) -lt 60
  initialDelaySeconds: 10
  periodSeconds: 30
```
:::warning
The file is only updated between iterations. Choose the threshold larger than your longest job
plus the sleep timer, otherwise a slow but healthy worker is considered dead.
:::

:::note
If the file cannot be written, the worker logs a warning and keeps running.
:::

## Logging

The worker logs its lifecycle (start, iteration timings, sleep, stop reason) to the given PSR-3 logger.
Iteration details use the `debug` level; stop reasons (limit exceeded, signal received) use `info`.

With the `ConsoleLogger` from the [Symfony command example](integration.md#symfony), run the command with `-v`
to see stop reasons or `-vvv` to see everything.

## Clock

The worker uses a [PSR-20](https://www.php-fig.org/psr/psr-20/) clock to measure the job's run time
and to check the `timeLimit`. By default it uses the system clock. You can pass your own clock to `create`,
e.g. a mock clock to test time-dependent behaviour without actually waiting:

```php
use Patchlevel\Worker\DefaultWorker;
use Symfony\Component\Clock\MockClock;

$worker = DefaultWorker::create(
    $job,
    ['timeLimit' => 3600],
    $logger,
    clock: new MockClock(),
);
```
:::note
The clock is only used for measuring time. The sleep between iterations still waits for real.
:::
