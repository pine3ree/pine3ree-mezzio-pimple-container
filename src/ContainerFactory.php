<?php

/**
 * @package    pine3ree-mezzio-pimple-container
 * @subpackage pine3ree-mezzio-pimple-container-test
 * @author     pine3ree https://github.com/pine3ree
 * @author     Laminas Project a Series of LF Projects, LLC. (Original library)
 */

declare(strict_types=1);

namespace pine3ree\Mezzio\Pimple;

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

    private bool $shared_by_default = true;

    public function __construct(array $config)
    {
        $this->config = $config;

        $dependencies = $config['dependencies'] ?? null;
        if (isset($dependencies) && is_array($dependencies)) {
            $this->dependencies = $dependencies;
            $shared_by_default  = $dependencies['shared_by_default'] ?? null;
            if (is_bool($shared_by_default)) {
                $this->shared_by_default = $shared_by_default;
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
            $pimple[$name] = fn() => $service;
        }
    }

    private function injectFactories(PimpleContainer $pimple, ContainerInterface $container): void
    {
        $factories = $this->dependencies['factories'] ?? null;

        if (empty($factories) || !is_array($factories)) {
            return;
        }

        foreach ($factories as $name => $factory) {
            $callback = function (PimpleContainer $pimple) use (
                $container,
                $factory,
                $name
            ) {
                if (!is_callable($factory)) {
                    $factory = $this->getInvokableInstance('factory', $factory, $pimple, $name);
                }
                return $factory($container, $name);
            };

            $delegators = $this->dependencies['delegators'][$name] ?? null;

            if (empty($delegators)) {
                $this->setService($pimple, $name, $callback);
            } else {
                $this->setServiceWithDelegators($pimple, $delegators, $container, $name, fn() => $callback($pimple));
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

            if (empty($delegators)) {
                $this->setService($pimple, $fqcn, $callback);
            } else {
                $this->setServiceWithDelegators($pimple, $delegators, $container, $fqcn, fn() => $callback($pimple));
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
                $pimple->extend(
                    $name,
                    function ($service, PimpleContainer $pimple) use (
                        $container,
                        $extension,
                        $name
                    ) {
                        if (!is_callable($extension)) {
                            $extension = $this->getInvokableInstance('extension', $extension, $pimple, $name);
                        }
                        // Passing extra parameter service $name
                        return $extension($service, $container, $name);
                    }
                );
            }
        }
    }

    /**
     * Delegator factory __invoke method signature;
     * public function MyDelegatorFactory::__invoke(ContainerInterface $container, string $name, callable $callback);
     */
    private function setServiceWithDelegators(
        PimpleContainer $pimple,
        array $delegators,
        ContainerInterface $container,
        string $name,
        callable $callback
    ) {
        $this->setService($pimple, $name, function (PimpleContainer $pimple) use (
            $delegators,
            $container,
            $name,
            $callback
        ) {
            foreach ($delegators as $delegatorFactory) {
                if (!is_callable($delegatorFactory)) {
                    $delegatorFactory = $this->getInvokableInstance('delegator', $delegatorFactory, $pimple, $name);
                }
                $callback = fn() => $delegatorFactory($container, $name, $callback);
            }
            return $callback();
        });
    }

    private function setService(PimpleContainer $pimple, string $name, callable $callback)
    {
        $pimple[$name] = $this->isShared($name) ? $callback : $pimple->factory($callback);
    }

    private function setAlias(PimpleContainer $pimple, string $alias, string $name)
    {
        $shared = $this->dependencies['shared'][$alias] ?? null;

        $callback = function (PimpleContainer $pimple) use ($alias, $name, $shared) {
            $service = $pimple->offsetGet($name);
            if ($shared === false) {
                return $this->isShared($name) ? clone $service : $service;
            }
            return $service;
        };

        $pimple[$alias] = $shared === true ? $callback : $pimple->factory($callback);
    }

    private function isShared(string $name): bool
    {
        $shared = $this->dependencies['shared'][$name] ?? null;

        if ($shared === null) {
            return $this->shared_by_default;
        }

        return $shared === true;
    }

    private function getInvokableInstance(string $type, string $class, PimpleContainer $pimple, string $name): object
    {
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
