[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fpatchlevel%2Fworker%2F1.6.x)](https://dashboard.stryker-mutator.io/reports/github.com/patchlevel/worker/1.6.x)
[![Latest Stable Version](https://poser.pugx.org/patchlevel/worker/v)](//packagist.org/packages/patchlevel/worker)
[![License](https://poser.pugx.org/patchlevel/worker/license)](//packagist.org/packages/patchlevel/worker)

# Worker

A small library to build stable, long-running workers that terminate gracefully when limits are exceeded
or a SIGTERM signal is received. Perfect for daemonized console commands running under
Docker, Kubernetes, supervisor or systemd, where the process manager restarts the worker after it exits.

## Features

* Configurable run, memory and time [limits](https://patchlevel.dev/docs/worker/latest/getting-started#limits)
* [Graceful shutdown](https://patchlevel.dev/docs/worker/latest/getting-started#graceful-shutdown-on-sigterm) on SIGTERM
* Extensible via [events and custom listeners](https://patchlevel.dev/docs/worker/latest/events)
* [PSR-3 logging](https://patchlevel.dev/docs/worker/latest/getting-started#logging) of the worker lifecycle
* Plays well with [Symfony and Laravel console commands](https://patchlevel.dev/docs/worker/latest/integration)

## Installation

```bash
composer require patchlevel/worker
```

## Documentation

* [Documentation](https://patchlevel.dev/docs/worker/latest)
* Related [Blog](https://patchlevel.dev/blog)
