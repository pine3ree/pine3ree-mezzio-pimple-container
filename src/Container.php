<?php

/**
 * @package pine3ree-mezzio-pimple-container
 * @author  pine3ree https://github.com/pine3ree
 */

namespace pine3ree\Mezzio\Pimple;

use Pimple\Container as PimpleContainer;
use Psr\Container\ContainerInterface;

/**
 * A PSR-11 compliant container extending a Pimple container
 */
final class Container extends PimpleContainer implements ContainerInterface
{
    public function get(string $id)
    {
        return $this->offsetGet($id);
    }

    public function has(string $id): bool
    {
        return $this->offsetExists($id);
    }
}
