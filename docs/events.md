# Events & Listeners

Internally the worker is built on the symfony event dispatcher. It dispatches three events,
each carrying the worker instance:

| Event                | Dispatched                          |
|----------------------|-------------------------------------|
| `WorkerStartedEvent` | once, before the first iteration    |
| `WorkerRunningEvent` | after every iteration               |
| `WorkerStoppedEvent` | once, after the worker has stopped  |

`WorkerStoppedEvent` is also dispatched if the job throws an exception.
In that case the exception is available via `$event->exception` and is rethrown afterwards,
so listeners can clean up resources and still distinguish a crash from a regular stop:

```php
use Patchlevel\Worker\Event\WorkerStoppedEvent;

$eventDispatcher->addListener(
    WorkerStoppedEvent::class,
    static function (WorkerStoppedEvent $event): void {
        if ($event->exception !== null) {
            // the worker crashed
        }
    },
);
```
All [limits](getting-started.md#limits) are implemented as event subscribers
(`StopWorkerOnIterationLimitListener`, `StopWorkerOnMemoryLimitListener`,
`StopWorkerOnTimeLimitListener`, `StopWorkerOnSignalListener`),
so you can add your own stop conditions the same way:

```php
use Patchlevel\Worker\Event\WorkerRunningEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class StopWorkerOnNewDeploymentListener implements EventSubscriberInterface
{
    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if (new_version_deployed()) {
            $event->worker->stop();
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [WorkerRunningEvent::class => 'onWorkerRunning'];
    }
}
```
Pass your own event dispatcher to `create` to register additional listeners:

```php
use Patchlevel\Worker\DefaultWorker;
use Symfony\Component\EventDispatcher\EventDispatcher;

$eventDispatcher = new EventDispatcher();
$eventDispatcher->addSubscriber(new StopWorkerOnNewDeploymentListener());

$worker = DefaultWorker::create(
    $job,
    ['timeLimit' => 3600],
    $logger,
    $eventDispatcher,
);
```
:::warning
`create` registers the limit listeners on the given event dispatcher.
If you create multiple workers with the same event dispatcher, all of them share each other's limits.
Use a separate event dispatcher per worker.
:::
