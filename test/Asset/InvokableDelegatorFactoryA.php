<?php

/**
 * @package    pine3ree-mezzio-pimple-container
 * @subpackage pine3ree-mezzio-pimple-container-test
 * @author     pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\test\Mezzio\Pimple\Asset;

use Psr\Container\ContainerInterface;

use pine3ree\test\Mezzio\Pimple\Asset\Invokable;
use pine3ree\test\Mezzio\Pimple\Asset\Delegator;

class InvokableDelegatorFactoryA
{
    public function __invoke(ContainerInterface $container, string $fqcn, callable $callback, ?array $options = null): Invokable
    {
        /** @var Invokable $invokable */
        $invokable = $callback();

        $invokable->setProperty('A', self::class);

        return $invokable;
    }
}
