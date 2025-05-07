<?php

/**
 * @package pine3ree-mezzio-pimple-container
 * @author  pine3ree https://github.com/pine3ree
 */

namespace pine3ree\Mezzio\Pimple;

use Pimple\Container as PimpleContainer;
use Pimple\Exception\UnknownIdentifierException;
use Psr\Container\ContainerInterface;
use pine3ree\Mezzio\Pimple\Exception\NotFoundException;

/**
 * A PSR-11 container
 */
final class Container implements ContainerInterface
{
    private PimpleContainer $pimple;

    public function __construct(PimpleContainer $pimple)
    {
        $this->pimple = $pimple;
    }

    /**
     * {@inheritDoc}
     *
     * @throws NotFoundException
     */
    public function get(string $id)
    {
        try {
            return $this->pimple->offsetGet($id);
        } catch (UnknownIdentifierException $ex) {
            throw new NotFoundException($ex->getMessage(), $ex->getCode());
        }
    }

    public function has(string $id): bool
    {
        return $this->pimple->offsetExists($id);
    }
}
