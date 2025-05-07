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
use Pimple\Psr11\Container as PsrContainer;
use Psr\Container\ContainerInterface;
use Throwable;
use pine3ree\Mezzio\Pimple\Exception\EmptyConfigurationException;
use pine3ree\Mezzio\Pimple\Exception\RuntimeException;

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
     * @param array<string, mixed>|null $config Optional configuration array used
     *      to initializethe 'config' service. If missing the 'config' service will
     *      be set to an empty array.
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

        $pimple = new PimpleContainer();
        $pimple->offsetSet('config', $config ?? []);

        $container = new PsrContainer($pimple);

        $this->injectServices($pimple, $dependencies);
        $this->injectFactories($pimple, $container, $dependencies);
        $this->injectInvokables($pimple, $container, $dependencies);
        $this->injectAliases($pimple, $dependencies);
        $this->injectExtensions($pimple, $container, $dependencies);

        return $container;
    }

    /**
     * @param array<string, array<string|int, mixed>> $dependencies
     */
    private function injectServices(PimpleContainer $pimple, array $dependencies): void
    {
        $services = $dependencies['services'] ?? null;

        if (empty($services) || !is_array($services)) {
            return;
        }

        foreach ($services as $name => $service) {
            if (is_object($service)) {
                if (is_callable($service)) {
                    $pimple[$name] = $pimple->protect($service);
                } elseif ($this->isShared($name, $dependencies)) {
                    $pimple[$name] = $service;
                } else {
                    $pimple[$name] = $pimple->factory(fn() => clone $service);
                }
            } else {
                $pimple[$name] = $service;
            }
        }
    }

    /**
     * @param array<string, array<string|int, mixed>> $dependencies
     */
    private function injectFactories(
        PimpleContainer $pimple,
        ContainerInterface $container,
        array $dependencies
    ): void {
        $factories = $dependencies['factories'] ?? null;

        if (empty($factories) || !is_array($factories)) {
            return;
        }

        foreach ($factories as $name => $factory) {
            // Pimple service-definition callbacks support a pimple-container
            // instance as argument, but we use a 0-arity callback so that it can
            // be used as a delegator-factory callback argument as well
            $callback = function () use (
                $pimple,
                $container,
                $factory,
                $name
            ) {
                $factory = $this->getFactory($factory, 'factory', $name, $pimple);
                return $factory($container, $name);
            };

            $delegators = $dependencies['delegators'][$name] ?? null;

            if (empty($delegators)) {
                $this->setService($pimple, $name, $callback, $dependencies);
            } elseif (is_array($delegators)) {
                $this->setServiceWithDelegators($pimple, $container, $name, $callback, $delegators, $dependencies);
            }
        }
    }

    /**
     * @param array<string, array<string|int, mixed>> $dependencies
     */
    private function injectInvokables(
        PimpleContainer $pimple,
        ContainerInterface $container,
        array $dependencies
    ): void {
        $invokables = $dependencies['invokables'] ?? null;

        if (empty($invokables) || !is_array($invokables)) {
            return;
        }

        foreach ($invokables as $alias => $fqcn) {
            // Pimple service-definition callbacks support a pimple-container
            // instance as argument, but we use a 0-arity callback so that it can
            // be used as a delegator-factory callback argument as well
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
                $this->setService($pimple, $fqcn, $callback, $dependencies);
            } elseif (is_array($delegators)) {
                $this->setServiceWithDelegators($pimple, $container, $fqcn, $callback, $delegators, $dependencies);
            }

            if (is_string($alias) && $alias !== $fqcn) {
                $this->setAlias($pimple, $alias, $fqcn, $dependencies);
            }
        }
    }

    /**
     * @param array<string, array<string|int, mixed>> $dependencies
     */
    private function injectAliases(PimpleContainer $pimple, array $dependencies): void
    {
        $aliases = $dependencies['aliases'] ?? null;

        if (empty($aliases) || !is_array($aliases)) {
            return;
        }

        foreach ($aliases as $alias => $name) {
            $this->setAlias($pimple, $alias, $name, $dependencies);
        }
    }

    /**
     * @param array<string, array<string|int, mixed>> $dependencies
     */
    private function injectExtensions(
        PimpleContainer $pimple,
        ContainerInterface $container,
        array $dependencies
    ): void {
        $extensions = $dependencies['extensions'] ?? null;

        if (empty($extensions) || !is_array($extensions)) {
            return;
        }

        foreach ($extensions as $name => $extensions) {
            foreach ($extensions as $extension) {
                $pimple->extend($name, function (
                    $service,
                    PimpleContainer $pimple
                ) use (
                    $container,
                    $extension,
                    $name
                ) {
                    $extensionFactory = $this->getFactory($extension, 'extension', $name, $pimple);
                    // Passing extra parameter service $name
                    return $extensionFactory($service, $container, $name);
                });
            }
        }
    }

    /**
     * Delegator factory __invoke method signature:
     * public function MyDelegatorFactory::__invoke(ContainerInterface $container, string $name, callable $callback);
     *
     * @param callable $callback The callback returning the original service or
     *      previouse delegator. It must not have any parameters defined.
     * @param array<string, array<int, string|object>> $delegators
     * @param array<string, array<string|int, mixed>> $dependencies
     */
    private function setServiceWithDelegators(
        PimpleContainer $pimple,
        ContainerInterface $container,
        string $name,
        callable $callback,
        array $delegators,
        array $dependencies
    ): void {
        $callback = function () use (
            $pimple,
            $delegators,
            $container,
            $name,
            $callback
        ) {
            foreach ($delegators as $delegator) {
                $delegatorFactory = $this->getFactory($delegator, 'delegator', $name, $pimple);
                $callback = fn() => $delegatorFactory($container, $name, $callback);
            }
            return $callback();
        };

        $this->setService($pimple, $name, $callback, $dependencies);
    }

    /**
     * @param array<string, array<string|int, mixed>> $dependencies
     */
    private function setService(PimpleContainer $pimple, string $name, callable $callback, array $dependencies): void
    {
        $pimple[$name] = $this->isShared($name, $dependencies) ? $callback : $pimple->factory($callback);
    }

    /**
     * @param array<string, array<string|int, mixed>> $dependencies
     */
    private function setAlias(PimpleContainer $pimple, string $alias, string $name, array $dependencies): void
    {
        $shared_alias   = $dependencies['shared'][$alias] ?? null;
        $shared_service = $this->isShared($name, $dependencies);

        $callback = function () use (
            $pimple,
            $name,
            $shared_alias,
            $shared_service
        ) {
            $service = $pimple->offsetGet($name);
            if ($shared_alias === false && is_object($service)) {
                return $shared_service ? clone $service : $service;
            }
            return $service;
        };

        $pimple[$alias] = $shared_alias === true ? $callback : $pimple->factory($callback);
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
     * @param string $type The type of factory (factory|delegator|extension)
     * @return callable
     * @throws ExpectedInvokableException
     */
    private function getFactory($objectOrClass, string $type, string $name, PimpleContainer $pimple)
    {
        if (is_object($objectOrClass)) {
            if (is_callable($objectOrClass)) {
                return $objectOrClass;
            }
            $class = get_class($objectOrClass);
            throw new ExpectedInvokableException(
                "The {$type} instance of type `{$class}` provided to initialize"
                . " service `{$name}` is not callable"
            );
        }

        if (!is_string($objectOrClass)) {
            throw new ExpectedInvokableException(
                "The `{$type}` provided to initialize service `{$name}` must be"
                . " an invokable instance or class"
            );
        }

        $class = $objectOrClass;
        if (!class_exists($class)) {
            throw new ExpectedInvokableException(
                "The {$type} class `{$class}` provided to initialize service"
                . " `{$name}` does not exist"
            );
        }

        if ($pimple->offsetExists($class)) {
            $factory = $pimple->offsetGet($class);
            if (!is_object($factory)) {
                throw new ExpectedInvokableException(
                    "The {$type} service class `{$class}` did not return an object"
                    . " from the container"
                );
            }
        } else {
            try {
                $factory = new $class();
            } catch (Throwable $ex) {
                throw new RuntimeException(
                    "The {$type} class `{$class}` provided to initialize service"
                    . " `{$name}` cannot be intantiated without arguments"
                );
            }
        }

        if (!is_callable($factory)) {
            throw new ExpectedInvokableException(
                "The {$type} class `{$class}` provided to initialize service"
                . " `{$name}` is not callable"
            );
        }

        // Store the factory instance into the container and protect it as callable-service
        if (!$pimple->offsetExists($class)) {
            $pimple[$class] = $pimple->protect($factory);
        }

        return $factory;
    }
}
