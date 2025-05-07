<?php

/**
 * @package pine3ree-mezzio-pimple-container
 * @author  pine3ree https://github.com/pine3ree (This "fork")
 * @author  Laminas Project a Series of LF Projects, LLC. (Original library: laminas-pimple-config)
 */

declare(strict_types=1);

namespace pine3ree\Mezzio\Pimple;

use Pimple\Container as PimpleContainer;
use Pimple\Exception\ExpectedInvokableException;
use Psr\Container\ContainerInterface;
use pine3ree\Mezzio\Pimple\Container;
use pine3ree\Mezzio\Pimple\Exception\EmptyConfigurationException;

use function class_exists;
use function get_class;
use function is_array;
use function is_bool;
use function is_callable;
use function is_object;
use function is_string;

/**
 * Create a psr Pimple\Psr11\Container using provided dependencies
 */
class ContainerFactory
{
    /**
     * The invokable factory method
     *
     * @param array<string, array<string|int, mixed>> $dependencies The dependency configuration array
     * @param array<string, mixed>|null $config Optional configuration array used to initialize
     *      the 'config' service. If missing the 'config' service will be set to
     *      an empty array.
     * @return ContainerInterface
     */
    public function __invoke(array $dependencies, ?array $config = null): ContainerInterface
    {
        if (empty($dependencies)
            || (empty($dependencies['services'])
                && empty($dependencies['invokables'])
                && empty($dependencies['factories'])
            )
        ) {
            throw new EmptyConfigurationException(
                "Unable to provide a container without defining at least one dependency"
            );
        }

        $container = new Container();
        $container->offsetSet('config', $config ?? []);

        $services = $dependencies['services'] ?? null;
        if (!empty($services) && is_array($services)) {
            $this->injectServices($container, $services, $dependencies);
        }

        $factories = $dependencies['factories'] ?? null;
        if (!empty($factories) && is_array($factories)) {
            $this->injectFactories($container, $factories, $dependencies);
        }

        $invokables = $dependencies['invokables'] ?? null;
        if (!empty($invokables) && is_array($invokables)) {
            $this->injectInvokables($container, $dependencies);
        }

        $aliases = $dependencies['aliases'] ?? null;
        if (!empty($aliases) && is_array($aliases)) {
            $this->injectAliases($container, $aliases, $dependencies);
        }

        $extensions = $dependencies['extensions'] ?? null;
        if (!empty($extensions) && is_array($extensions)) {
            $this->injectExtensions($container, $extensions, $dependencies);
        }


        return $container;
    }

    /**
     * @param array<string|int, mixed> $services
     * @param array<string, array<string|int, mixed>> $dependencies
     */
    private function injectServices(Container $container, array $services, array $dependencies): void
    {
        foreach ($services as $name => $service) {
            if (is_object($service)) {
                if ($this->isShared($name, $dependencies)) {
                    $container[$name] = $service;
                } elseif (is_callable($service)) {
                    $container->factory($service);
                } else {
                    $container->factory(fn() => clone $service);
                }
            } else {
                $container[$name] = $service;
            }
        }
    }

    /**
     * @param array<string, mixed> $factories
     * @param array<string, array<string|int, mixed>> $dependencies
     */
    private function injectFactories(Container $container, array $factories, array $dependencies): void
    {
        foreach ($factories as $name => $factory) {
            $callback = function () use (
                $container,
                $factory,
                $name
            ) {
                $factory = $this->getFactoryFor($factory, 'factory', $container, $name);
                return $factory($container, $name);
            };

            $delegators = $dependencies['delegators'][$name] ?? null;

            if (empty($delegators)) {
                $this->setService($container, $dependencies, $name, $callback);
            } elseif (is_array($delegators)) {
                $this->setServiceWithDelegators($dependencies, $delegators, $container, $name, $callback);
            }
        }
    }

    /**
     * @param array<string, array<string|int, mixed>> $dependencies
     */
    private function injectInvokables(Container $container, array $invokables, array $dependencies): void
    {
        foreach ($invokables as $alias => $fqcn) {
            $callback = function () use (
                $fqcn
            ) {
                if (!class_exists($fqcn)) {
                    throw new ExpectedInvokableException(
                        "The invokable service class `{$fqcn}` does not exist"
                    );
                }
                return new $fqcn();
            };

            $delegators = $dependencies['delegators'][$fqcn] ?? null;

            if (empty($delegators)) {
                $this->setService($container, $dependencies, $fqcn, $callback);
            } elseif (is_array($delegators)) {
                $this->setServiceWithDelegators($dependencies, $delegators, $container, $fqcn, $callback);
            }

            if (is_string($alias) && $alias !== $fqcn) {
                $this->setAlias($container, $dependencies, $alias, $fqcn);
            }
        }
    }

    /**
     * @param array<string, array<string|int, mixed>> $dependencies
     */
    private function injectAliases(Container $container, array $aliases, array $dependencies): void
    {
        foreach ($aliases as $alias => $name) {
            $this->setAlias($container, $dependencies, $alias, $name);
        }
    }

    /**
     * @param array<string, array<string|int, mixed>> $dependencies
     */
    private function injectExtensions(ContainerInterface $container, array $extensions, array $dependencies): void
    {
        foreach ($extensions as $name => $extensions) {
            foreach ($extensions as $extension) {
                $container->extend($name, function (
                    $service,
                    PimpleContainer $container
                ) use (
                    $extension,
                    $name
                ) {
                    $extensionFactory = $this->getFactoryFor($extension, 'extension', $container, $name);
                    // Passing extra parameter service $name
                    return $extensionFactory($service, $container, $name);
                });
            }
        }
    }

    /**
     * Delegator factory __invoke method signature;
     * public function MyDelegatorFactory::__invoke(ContainerInterface $container, string $name, callable $callback);
     *
     * @param array<string, array<string|int, mixed>> $dependencies
     * @param array<string, array<int, string|object>> $delegators
     */
    private function setServiceWithDelegators(
        array $dependencies,
        array $delegators,
        ContainerInterface $container,
        string $name,
        callable $callback
    ): void {
        $this->setService($container, $dependencies, $name, function () use (
            $delegators,
            $container,
            $name,
            $callback
        ) {
            foreach ($delegators as $delegator) {
                $delegatorFactory = $this->getFactoryFor($delegator, 'delegator', $container, $name);
                $callback = fn() => $delegatorFactory($container, $name, $callback);
            }
            return $callback();
        });
    }

    /**
     * @param array<string, array<string|int, mixed>> $dependencies
     */
    private function setService(Container $container, array $dependencies, string $name, callable $callback): void
    {
        $container[$name] = $this->isShared($name, $dependencies) ? $callback : $container->factory($callback);
    }

    /**
     * @param array<string, array<string|int, mixed>> $dependencies
     */
    private function setAlias(Container $container, array $dependencies, string $alias, string $name): void
    {
        $shared_alias   = $dependencies['shared'][$alias] ?? null;
        $shared_service = $this->isShared($name, $dependencies);

        $callback = function () use (
            $container,
            $name,
            $shared_alias,
            $shared_service
        ) {
            $service = $container->offsetGet($name);
            if ($shared_alias === false && is_object($service)) {
                return $shared_service ? clone $service : $service;
            }
            return $service;
        };

        $container[$alias] = $shared_alias === true ? $callback : $container->factory($callback);
    }

    /**
     * @param array<string, array<string|int, mixed>> $dependencies
     */
    private function isShared(string $name, array $dependencies): bool
    {
        $name_is_shared = $dependencies['shared'][$name] ?? null;
        if (is_bool($name_is_shared)) {
            return $name_is_shared;
        }

        $shared_by_default = $dependencies['shared_by_default'] ?? true;
        if (is_bool($shared_by_default)) {
            return $shared_by_default;
        }

        return true;
    }

    /**
     * Validate an invokable-factory instance or class and return an instance of it
     *
     * @param class-string|callable|object|mixed $objectOrClass
     * @param string $type the type of factory
     * @return callable
     * @throws ExpectedInvokableException
     */
    private function getFactoryFor($objectOrClass, string $type, Container $container, string $name)
    {
        if (is_object($objectOrClass)) {
            $factory = $objectOrClass;
            $class = get_class($objectOrClass);
        } else {
            if (!is_string($objectOrClass)) {
                throw new ExpectedInvokableException(
                    "The argument provided must be an invokable instance or class"
                );
            }
            $class = $objectOrClass;
            if (!class_exists($class)) {
                throw new ExpectedInvokableException(
                    "The {$type} class `{$class}` provided to initialize service `{$name}` does not exist"
                );
            }
            if ($container->offsetExists($class)) {
                $factory = $container->offsetGet($class);
                if (!is_object($factory)) {
                    throw new ExpectedInvokableException(
                        "The {$type} service class `{$class}` did not return an object from the container"
                    );
                }
            } else {
                $factory = new $class();
            }
        }

        if (!is_callable($factory)) {
            throw new ExpectedInvokableException(
                "The {$type} class `{$class}` provided to initialize service `{$name}` is not callable"
            );
        }

        // Store the callable delegator instance into the container and protect it as callback
        if (!$container->offsetExists($class)) {
            $container[$class] = $container->protect($factory);
        }

        return $factory;
    }
}
