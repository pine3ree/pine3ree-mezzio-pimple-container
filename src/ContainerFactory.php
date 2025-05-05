<?php

declare(strict_types=1);

namespace pine3ree\Mezzio\Container\Pimple;

use Pimple\Container as PimpleContainer;
use Pimple\Exception\ExpectedInvokableException;
use Pimple\Psr11\Container as PsrContainer;
use Psr\Container\ContainerInterface;

use function class_exists;
use function is_array;
use function is_bool;
use function is_callable;
use function is_string;

/**
 * Create a psr Pimple\Psr11\Container using provided configuration
 */
class ContainerFactory
{
    private array $config;
    private array $dependencies = [];

    private bool $sharedByDefault = true;

    public function __construct(array $config)
    {
        $this->config = $config;

        $dependencies = $config['dependencies'] ?? null;
        if (isset($dependencies) && is_array($dependencies)) {
            $this->dependencies = $dependencies;
            $sharedByDefault = $dependencies['shared_by_default'] ?? null;
            if (is_bool($sharedByDefault)) {
                $this->sharedByDefault = $sharedByDefault;
            }
        }
    }

    public function __invoke(): ContainerInterface
    {
        $pimple = new PimpleContainer();
        $pimple['config'] = $this->config;

        $container = new PsrContainer($pimple);

        $this->injectServices($pimple);
        $this->injectFactories($pimple, $container);
        $this->injectInvokables($pimple, $container);
        $this->injectAliases($pimple);
        $this->injectExtensions($pimple, $container);

        return $container;
    }

    private function injectServices(PimpleContainer $pimple): void
    {
        $services = $this->dependencies['services'] ?? null;

        if (empty($services) || !is_array($services)) {
            return;
        }

        foreach ($services as $name => $service) {
            $pimple[$name] = $pimple->protect($service);
        }
    }

    private function injectFactories(PimpleContainer $pimple, ContainerInterface $container): void
    {
        $factories = $this->dependencies['factories'] ?? null;

        if (empty($factories) || !is_array($factories)) {
            return;
        }

        foreach ($factories as $name => $factory) {
            $callback = function (PimpleContainer $pimple) use ($container, $factory, $name) {
                if (!is_callable($factory)) {
                    $factory = $this->getCallableInstance('factory', $factory, $pimple, $name);
                }
//                if (is_callable($factory)) {
//                    return $factory($container, $name);
//                }
//
//                if (!is_string($factory) || !class_exists($factory)) {
//                    throw new ExpectedInvokableException(
//                        "The factory class provided to initialize service `{$name}` does not exist"
//                    );
//                }
//
//                $factoryClass = $factory;
//                $factory = $pimple[$factoryClass] ?? new $factoryClass();
//                if (!is_callable($factory)) {
//                    throw new ExpectedInvokableException(
//                        "The factory class `{$factoryClass}` provided to initialize service `{$name}` is not callable"
//                    );
//                }
//
//                // Store the callable factory instance into the container (protecting it)
//                if (!$pimple->offsetExists($factoryClass)) {
//                    $pimple[$factoryClass] = $pimple->protect($factory);
//                }

                return $factory($container, $name);
            };

            $delegators = $this->dependencies['delegators'][$name] ?? null;

            if (!empty($delegators)) {
                $this->injectDelegators($pimple, $container, $delegators, $name, $callback);
            } else {
                $this->setService($pimple, $name, $callback);
            }
        }
    }

    private function injectInvokables(PimpleContainer $pimple, ContainerInterface $container): void
    {
        $invokables = $this->dependencies['invokables'] ?? null;

        if (empty($invokables) || !is_array($invokables)) {
            return;
        }

        foreach ($invokables as $alias => $fqcn) {
            $callback = function () use ($fqcn) {
                if (!class_exists($fqcn)) {
                    throw new ExpectedInvokableException(
                        "The invokable service class `{$fqcn}` does not exist"
                    );
                }

                return new $fqcn();
            };

            $delegators = $this->dependencies['delegators'][$fqcn] ?? null;

            if (!empty($delegators)) {
                $this->injectDelegators($pimple, $container, $delegators, $fqcn, $callback);
            } else {
                $this->setService($pimple, $fqcn, $callback);
            }

            if (is_string($alias) && $alias !== $fqcn) {
                $this->setAlias($pimple, $alias, $fqcn);
            }
        }
    }

    private function injectAliases(PimpleContainer $pimple): void
    {
        $aliases = $this->dependencies['aliases'] ?? null;

        if (empty($aliases) || !is_array($aliases)) {
            return;
        }

        foreach ($aliases as $alias => $name) {
            $this->setAlias($pimple, $alias, $name);
        }
    }

    private function injectExtensions(PimpleContainer $pimple, ContainerInterface $container): void
    {
        $extensions = $this->dependencies['extensions'] ?? null;

        if (empty($extensions) || !is_array($extensions)) {
            return;
        }

        foreach ($extensions as $name => $extensions) {
            foreach ($extensions as $extension) {
                $pimple->extend($name, function ($service, PimpleContainer $pimple) use (
                    $container,
                    $extension,
                    $name
                ) {
                    if (!is_callable($extension)) {
                        $extension = $this->getCallableInstance('extension', $extension, $pimple, $name);
                    }
//                    $factory = $pimple[$extension] ?? new $extension();
                    // Passing extra parameter service $name
                    return $factory($service, $container, $name);
                });
            }
        }
    }

    /**
     * Delegator factory __invoke method signature;
     * public function MyDelegatorFactory::__invoke(ContainerInterface $container, string $name, callable $callback);
     */
    private function injectDelegators(
        PimpleContainer $pimple,
        ContainerInterface $container,
        array $delegators,
        string $name,
        callable $callback
    ) {
        $pimple[$name] = function (PimpleContainer $pimple) use (
            $container,
            $name,
            $callback,
            $delegators
        ) {
            foreach ($delegators as $delegator) {
                if (!is_callable($delegator)) {
                    $delegator = $this->getCallableInstance('delegator', $delegator, $pimple, $name);
                }
//                if (is_callable($delegator)) {
//                    $callback = fn() => $delegator($container, $name, $callback);
//                    continue;
//                }
//
//                if (!is_string($delegator) || !class_exists($delegator)) {
//                    throw new ExpectedInvokableException(
//                        "A delegator factory must be a valid class name"
//                    );
//                }
//
//                $delegatorClass = $delegator;
//                $delegator = $pimple[$delegatorClass] ?? new $delegatorClass();
//                if (!is_callable($delegator)) {
//                    throw new ExpectedInvokableException(
//                        "The delegator class `{$delegatorClass}` provided to initialize service `{$name}` is not callable"
//                    );
//                }
//
//                // Store the callable delegator instance into the container (protecting it)
//                if (!$pimple->offsetExists($delegatorClass)) {
//                    $pimple[$delegatorClass] = $pimple->protect($delegator);
//                }

                $callback = fn() => $delegator($container, $name, $callback);
            }

            return $callback();
        };
    }

    private function setService(PimpleContainer $pimple, string $name, callable $callback)
    {
        $pimple[$name] = $this->isShared($name) ? $callback : $pimple->factory($callback);
    }

    private function setAlias(PimpleContainer $pimple, string $alias, string $name)
    {
        $pimple[$alias] = fn() => $pimple->offsetGet($name);
    }

    private function isShared(string $name): bool
    {
        $shared = $this->dependencies['shared'][$name] ?? null;

        if ($this->sharedByDefault && $shared === null) {
            return true;
        }

        return $shared === true;
    }

    private function getCallableInstance(string $type, $objectOrClass, PimpleContainer $pimple, string $name): object
    {
//        if (is_object($objectOrClass) && is_callable($objectOrClass)) {
//            return $objectOrClass;
//        }
//
        if (!is_string($objectOrClass) || !class_exists($objectOrClass)) {
            throw new ExpectedInvokableException(
                "The {$type} class provided to initialize service `{$name}` does not exist"
            );
        }

        $class  = $objectOrClass;
        $object = $pimple[$class] ?? new $class();
        if (!is_callable($object)) {
            throw new ExpectedInvokableException(
                "The {$type} class `{$class}` provided to initialize service `{$name}` is not callable"
            );
        }

        // Store the callable delegator instance into the container (protecting it)
        if (!$pimple->offsetExists($class)) {
            $pimple[$class] = $pimple->protect($object);
        }

        return $object;
    }
}
