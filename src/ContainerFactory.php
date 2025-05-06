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
                $pimple[$name] = $this->isShared($name, $dependencies)
                    ? fn() => $service
                    : $pimple->factory(fn() => clone $service);
            } else {
                $pimple[$name] = $service;
            }
        }
    }

    /**
     * @param array<string, array<string|int, mixed>> $dependencies
     */
    private function injectFactories(PimpleContainer $pimple, ContainerInterface $container, array $dependencies): void
    {
        $factories = $dependencies['factories'] ?? null;

        if (empty($factories) || !is_array($factories)) {
            return;
        }

        foreach ($factories as $name => $factory) {
            $callback = function () use (
                $pimple,
                $container,
                $factory,
                $name
            ) {
                if (is_string($factory) || !is_callable($factory)) {
                    $factory = $this->getInvokableInstance($pimple, 'factory', $factory, $name);
                }
                return $factory($container, $name);
            };

            $delegators = $dependencies['delegators'][$name] ?? null;

            if (empty($delegators)) {
                $this->setService($pimple, $dependencies, $name, $callback);
            } elseif (is_array($delegators)) {
                $this->setServiceWithDelegators($pimple, $dependencies, $delegators, $container, $name, $callback);
            }
        }
    }

    /**
     * @param array<string, array<string|int, mixed>> $dependencies
     */
    private function injectInvokables(PimpleContainer $pimple, ContainerInterface $container, array $dependencies): void
    {
        $invokables = $dependencies['invokables'] ?? null;

        if (empty($invokables) || !is_array($invokables)) {
            return;
        }

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
                $this->setService($pimple, $dependencies, $fqcn, $callback);
            } elseif (is_array($delegators)) {
                $this->setServiceWithDelegators($pimple, $dependencies, $delegators, $container, $fqcn, $callback);
            }

            if (is_string($alias) && $alias !== $fqcn) {
                $this->setAlias($pimple, $dependencies, $alias, $fqcn);
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
            $this->setAlias($pimple, $dependencies, $alias, $name);
        }
    }

    /**
     * @param array<string, array<string|int, mixed>> $dependencies
     */
    private function injectExtensions(PimpleContainer $pimple, ContainerInterface $container, array $dependencies): void
    {
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
                    if (is_string($extension) || !is_callable($extension)) {
                        $extension = $this->getInvokableInstance($pimple, 'extension', $extension, $name);
                    }
                    // Passing extra parameter service $name
                    return $extension($service, $container, $name);
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
        PimpleContainer $pimple,
        array $dependencies,
        array $delegators,
        ContainerInterface $container,
        string $name,
        callable $callback
    ): void {
        $this->setService($pimple, $dependencies, $name, function () use (
            $pimple,
            $delegators,
            $container,
            $name,
            $callback
        ) {
            foreach ($delegators as $delegatorFactory) {
                if (is_string($delegatorFactory) || !is_callable($delegatorFactory)) {
                    $delegatorFactory = $this->getInvokableInstance($pimple, 'delegator', $delegatorFactory, $name);
                }
                $callback = fn() => $delegatorFactory($container, $name, $callback);
            }
            return $callback();
        });
    }

    /**
     * @param array<string, array<string|int, mixed>> $dependencies
     */
    private function setService(PimpleContainer $pimple, array $dependencies, string $name, callable $callback): void
    {
        $pimple[$name] = $this->isShared($name, $dependencies) ? $callback : $pimple->factory($callback);
    }

    /**
     * @param array<string, array<string|int, mixed>> $dependencies
     */
    private function setAlias(PimpleContainer $pimple, array $dependencies, string $alias, string $name): void
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
     * @param class-string|callable|mixed $objectOrClass
     * @return callable
     * @throws ExpectedInvokableException
     */
    private function getInvokableInstance(PimpleContainer $pimple, string $type, $objectOrClass, string $name)
    {
        if (is_object($objectOrClass)) {
            $callable = $objectOrClass;
            $class = get_class($objectOrClass);
        } else {
            if (!is_string($objectOrClass)) {
                throw new ExpectedInvokableException(
                    "The argument provided must be a callable object instance or class"
                );
            }
            $class = $objectOrClass;
            if (!class_exists($class)) {
                throw new ExpectedInvokableException(
                    "The {$type} class `{$class}` provided to initialize service `{$name}` does not exist"
                );
            }
            if ($pimple->offsetExists($class)) {
                $callable = $pimple->offsetGet($class);
                if (!is_object($callable)) {
                    throw new ExpectedInvokableException(
                        "The {$type} service class `{$class}` did not return an object from the container"
                    );
                }
            } else {
                $callable = new $class();
            }
        }

        if (!is_callable($callable)) {
            throw new ExpectedInvokableException(
                "The {$type} class `{$class}` provided to initialize service `{$name}` is not callable"
            );
        }

        // Store the callable delegator instance into the container and protect it as callback
        if (!$pimple->offsetExists($class)) {
            $pimple[$class] = $pimple->protect($callable);
        }

        return $callable;
    }
}
