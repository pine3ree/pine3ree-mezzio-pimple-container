# pine3ree-mezzio-pimple-container

[![Continuous Integration](https://github.com/pine3ree/pine3ree-mezzio-pimple-container/actions/workflows/continuos-integration.yml/badge.svg)](https://github.com/pine3ree/pine3ree-mezzio-pimple-container/actions/workflows/continuos-integration.yml)

This package provides a factory for Pimple\Psr11\Container instances to be used
in Mezzio applications

It is actually a fork of the abandoned library [laminas-pimple-config](https://github.com/laminas/laminas-pimple-config)
modified in order to use a single pimple-psr-container. Most of the following
text is taken from it.

## Installation

Run the following to install this library:

```bash
$ composer require pine3ree/pine3ree-mezzio-pimple-container
```

## Configuration

To get a configured [PSR-11](http://www.php-fig.org/psr/psr-11/)
Pimple container, do the following:

```php
<?php

use pine3ree\Mezzio\Pimple\ContainerFactory;

$dependencies = [
    'services'          => [], // Resolved objects/services
    'invokables'        => [], // Simple constructor-less classes
    'factories'         => [], // Callable factories or callable factory classes for complex objects
    'aliases'           => [], // Aliases for other services
    'delegators'        => [], // Delegator factories (callables or classes)
    'extensions'        => [], // Pimple-like extension factories
    'shared'            => [], // Per-class overrides of the default sharing mode
    'shared_by_default' => true, // Optional, defaults to TRUE if omitted
];

$factory = new ContainerFactory();
$container = $factory($dependencies); // Psr\Container\ContainerInterface|Pimple\Psr11\Container
```

Usually the dependency configuration is part of the application configuration:
```php
<?php

$config = [
    // Configuration key related to the container
    'dependencies' => [
        'services'          => [], // Resolved objects/services
        'invokables'        => [], // Simple constructor-less classes
        'factories'         => [], // Callable factories or callable factory classes for complex objects
        'aliases'           => [], // Aliases for other services
        'delegators'        => [], // Delegator factories (callables or classes)
        'extensions'        => [], // Pimple-like extension factories
        'shared'            => [], // Per-class overrides of the default sharing mode
        'shared_by_default' => true, // Defaults to `true` if omitted
    ],
    // ... other configuration
];

$factory = new ContainerFactory();
$container = $factory($config['dependencies'], $config);
```

The `dependencies` array can contain the following keys:

- `services`: an associative array that maps a key to a specific service instance
  (or pimple parameter value)

- `invokables`: an associative array that map a key to a constructor-less
  service class; i.e., for services that do not require arguments to the
  constructor. The key and service name usually are the same; if they are not, the key is
  treated as an alias. The key can be omitted if equal to the service class.

- `factories`: an associative array that maps a service name to a factory class
  name, or any callable. Factory classes must be instantiable without arguments,
  and callable once instantiated (i.e., implement the `__invoke()` method).

- `aliases`: an associative array that maps an alias to a service name (or
  another alias).

- `delegators`: an associative array that maps service names to lists of
  delegator factory classes. Delegator factories are commonly used to customize
  and/or decorate the original service.

- `extensions`: an associative array that maps service names to lists of
  extension factory classes, see the [the section below](#extensions).

- `shared`: associative array that map a service name to a boolean, in order to
  indicate the container if it should cache or not a service created
  through the get method, overriding the shared_by_default setting.

- `shared_by_default`: boolean that indicates whether services created through
  the `get` method should be cached. This is `true` by default.

> Please note: the `services`, `invokables` and `factories` configurations cannot
> all be empty, that is at least one service must be configured.

> Please note that when injected by the factory the whole configuration is
> available in the `$container` on the `config` key:
>
> ```php
> $config = $container->get('config');
> ```

### `extensions`

> Since the `extensions` configuration is only available with the Pimple container,
> it is recommended to use `delegators` in order to keep the highest compatibility
> and allow easier switch to other container libraries.

An extension factory is an invokable object with the following signature:

```php
use Psr\Container\ContainerInterface;

public function __invoke(
    $service,
    ContainerInterface $container,
    $name
);
```

The parameters passed to the extension factory are the following:

- `$service` is the real service instance.
- `$container` is the psr-container that is used while creating the extension for
  the requested service.
- `$name` is the name of the service being requested.

Here is an example extension factory:

```php
<?php

use App\Service\MyService; // implements MyServiceInterface
use App\Service\MyServiceInterface;
use Psr\Container\ContainerInterface;

class ExtensionFactory
{
    public function __invoke(MyService $service, ContainerInterface $container, $name): MyService
    {
        // do something with $service

        return $service;
    }
}
```

You can also return a different instance from the extension factory:

```php
use App\Service\MyService; // implements MyServiceInterface
use App\Service\MyServiceDecorator; // implements MyServiceInterface
use App\Service\MyServiceInterface;
use Psr\Container\ContainerInterface;

class ExtensionFactory
{
    public function __invoke(MyServiceInterface $service, ContainerInterface $container, $name): MyServiceInterface
    {
        return new MyServiceDecorator($service);
    }
}
```

Please note that when configuring extensions, you must provide a _list_ of
extension factories for the service, and not a single extension factory name:

```php
$dependencies = [
    'invokables' => [
        'my-service' => MyInvokable\Service::class,
    ],
    'extensions' => [
        'my-service' => [
            Extension1Factory::class,
            Extension2Factory::class,
            // ...
        ],
    ],
];
```

Service extensions are called in the same order as defined in the list.

## Using with Mezzio

Replace contents of `config/container.php` with the following:

```php
<?php
// file: config/container.php

use pine3ree\Mezzio\Pimple\ContainerFactory;

$config  = require __DIR__ . '/config.php';
$factory = new ContainerFactory();

return $factory($config['dependencies], $config);
```
