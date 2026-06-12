# Events & Listeners

Internally the worker is built on the symfony event dispatcher. It dispatches three events,
each carrying the worker instance:

| Event                | Dispatched                          |
|----------------------|-------------------------------------|
| `WorkerStartedEvent` | once, before the first iteration    |
| `WorkerRunningEvent` | after every iteration               |
| `WorkerStoppedEvent` | once, after the worker has stopped  |

All [limits](getting-started.md#limits) are implemented as event subscribers
(`StopWorkerOnIterationLimitListener`, `StopWorkerOnMemoryLimitListener`,
`StopWorkerOnTimeLimitListener`, `StopWorkerOnSigtermSignalListener`),
so you can add your own stop conditions the same way:

```php
use Patchlevel\Worker\Event\WorkerRunningEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class StopWorkerOnNewDeploymentListener implements EventSubscriberInterface
{
    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if (/* new version deployed */) {
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
