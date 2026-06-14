# Worker

A small library to build stable, long-running workers that terminate gracefully when limits are exceeded
or a SIGTERM signal is received. Perfect for daemonized console commands running under
Docker, Kubernetes, supervisor or systemd, where the process manager restarts the worker after it exits.

It was extracted from the [event-sourcing](https://github.com/patchlevel/event-sourcing) library into a separate package.

## Features

* Configurable run, memory and time [limits](getting-started.md#limits)
* [Graceful shutdown](getting-started.md#graceful-shutdown-on-sigterm) on SIGTERM
* Extensible via [events and custom listeners](events.md)
* [PSR-3 logging](getting-started.md#logging) of the worker lifecycle
* Plays well with [Symfony and Laravel console commands](integration.md)

## Installation

```bash
composer require patchlevel/worker
```

:::tip
Start with the [getting started](getting-started.md) guide to get a feeling for the library.
:::
