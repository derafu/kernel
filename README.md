# Derafu: Kernel - Lightweight Kernel Implementation with DI Container

![GitHub last commit](https://img.shields.io/github/last-commit/derafu/kernel/main)
![CI Workflow](https://github.com/derafu/kernel/actions/workflows/ci.yml/badge.svg?branch=main&event=push)
![GitHub code size in bytes](https://img.shields.io/github/languages/code-size/derafu/kernel)
![GitHub Issues](https://img.shields.io/github/issues-raw/derafu/kernel)
![Total Downloads](https://poser.pugx.org/derafu/kernel/downloads)
![Monthly Downloads](https://poser.pugx.org/derafu/kernel/d/monthly)

A lightweight kernel implementation with a dependency injection container, inspired by Symfony but with a minimal approach.

## Overview

Derafu Kernel provides a clean, flexible foundation for PHP applications with minimal dependencies. It offers:

- A lightweight kernel implementation with DI container.
- Environment-aware configuration management.
- Support for PHP and YAML configuration files.
- Container caching for improved performance.
- Built-in support for multiple environments (dev, prod, test, etc.).

## Installation

```bash
composer require derafu/kernel
```

## Basic Usage

### Create a Kernel

The simplest way to use the kernel is to create an instance with a specific environment:

```php
use Derafu\Kernel\MicroKernel;

// Create a kernel with 'dev' environment and debug mode enabled.
$kernel = new MicroKernel('dev', true);

// Boot the kernel to initialize the container.
$kernel->boot();
```

### Custom Kernel Implementation

You can extend the `MicroKernel` class to customize its behavior:

```php
use Derafu\Kernel\MicroKernel;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

class AppKernel extends MicroKernel
{
    // Override the default configuration files.
    protected const CONFIG_FILES = [
        'services.php' => 'php',
        'routes.php' => 'routes',
        'parameters.yaml' => 'yaml',
    ];

    // Add additional container configuration.
    protected function configure(ContainerConfigurator $configurator): void
    {
        $services = $configurator->services();

        // Register application services.
        $services->set('app.service', MyService::class)
            ->public()
            ->args(['param1', 'param2']);
    }
}
```

### Environment Configuration

The kernel uses an environment object to determine settings and directories:

```php
use Derafu\Kernel\Environment;

// Create an environment with custom settings.
$environment = new Environment(
    'prod',                 // Environment name.
    false,                  // Debug mode.
    ['custom' => 'value']   // Context variables.
);

// Create a kernel with the custom environment.
$kernel = new MicroKernel($environment);
```

## Configuration

### Directory Structure

The kernel expects a `config` directory for configurations. Any other structure is free.

### Service Configuration

Define services in `config/services.php`:

```php
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator) {
    $services = $configurator->services();

    // Auto-configure and autowire services by default.
    $services->defaults()
        ->autowire()
        ->autoconfigure();

    // Register a single service.
    $services->set('app.service', AppService::class)
        ->public();

    // Register multiple services from namespace.
    $services->load('App\\Service\\', '../src/Service/*')
        ->public();
};
```

### Route Configuration

Define routes in `config/routes.php` or `config/routes.yaml`:

```php
// routes.php
return [
    'home' => [
        'path' => '/',
        'controller' => 'App\\Controller\\HomeController::index',
    ],
    'blog_show' => [
        'path' => '/blog/{slug}',
        'controller' => 'App\\Controller\\BlogController::show',
        'parameters' => [
            'requirements' => [
                'slug' => '[a-z0-9-]+',
            ],
        ],
    ],
];
```

Or in YAML:

```yaml
# routes.yaml
home:
    path: /
    controller: App\Controller\HomeController::index

blog_show:
    path: /blog/{slug}
    controller: App\Controller\BlogController::show
    parameters:
        requirements:
            slug: '[a-z0-9-]+'
```

## Environment Types

The kernel supports multiple environment types through constants in `EnvironmentInterface`:

- `LOCAL`: Local development environment.
- `DEVELOPMENT`: Development environment.
- `TEST`: Testing environment.
- `STAGING`: Staging environment.
- `QUALITY_ASSURANCE`: QA environment.
- `PREPRODUCTION`: Pre-production environment.
- `PRODUCTION`: Production environment.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

Happy coding! ✨
