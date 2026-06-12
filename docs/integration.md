# Integration

A typical setup is a console command that exposes the [limits](getting-started.md#limits) as options,
so they can be configured per environment.

## Symfony

```php
<?php

declare(strict_types=1);

namespace App\Console\Command;

use Patchlevel\Worker\DefaultWorker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('app:worker', 'do stuff')]
final class WorkerCommand
{
    public function __invoke(
        OutputInterface $output,
        #[Option(description: 'The maximum number of runs this command should execute')]
        int|null $runLimit = null,
        #[Option(description: 'How much memory consumption should the worker be terminated (500MB, 1GB, etc.)')]
        string|null $memoryLimit = null,
        #[Option(description: 'What is the maximum time the worker can run in seconds')]
        int|null $timeLimit = null,
        #[Option(description: 'How much time should elapse before the next job is executed in milliseconds')]
        int $sleep = 1000,
    ): int {
        $logger = new ConsoleLogger($output);

        $worker = DefaultWorker::create(
            function (callable $stop): void {
                // do something

                if (/* some condition */) {
                    $stop();
                }
            },
            [
                'runLimit' => $runLimit,
                'memoryLimit' => $memoryLimit,
                'timeLimit' => $timeLimit,
            ],
            $logger
        );

        $worker->run($sleep);

        return Command::SUCCESS;
    }
}
```

Run it with the limits suited to your deployment:

```bash
bin/console app:worker --time-limit=3600 --memory-limit=512MB -v
```

## Laravel

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Patchlevel\Worker\DefaultWorker;
use Psr\Log\LoggerInterface;

#[Signature('app:worker
    {--run-limit= : The maximum number of runs this command should execute}
    {--memory-limit= : How much memory consumption should the worker be terminated (500MB, 1GB, etc.)}
    {--time-limit= : What is the maximum time the worker can run in seconds}
    {--sleep=1000 : How much time should elapse before the next job is executed in milliseconds}')]
#[Description('do stuff')]
final class WorkerCommand extends Command
{
    public function handle(LoggerInterface $logger): int
    {
        $runLimit = $this->option('run-limit');
        $timeLimit = $this->option('time-limit');

        $worker = DefaultWorker::create(
            function (callable $stop): void {
                // do something

                if (/* some condition */) {
                    $stop();
                }
            },
            [
                'runLimit' => $runLimit !== null ? (int) $runLimit : null,
                'memoryLimit' => $this->option('memory-limit'),
                'timeLimit' => $timeLimit !== null ? (int) $timeLimit : null,
            ],
            $logger,
        );

        $worker->run((int) $this->option('sleep'));

        return self::SUCCESS;
    }
}
```

Run it with the limits suited to your deployment:

```bash
php artisan app:worker --time-limit=3600 --memory-limit=512MB
```

:::note
The injected `LoggerInterface` writes to Laravel's default log channel.
Use a dedicated channel if you want the worker output separated from the rest of your application logs.
:::
