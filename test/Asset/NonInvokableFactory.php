<?php

/**
 * @package    pine3ree-mezzio-pimple-container
 * @subpackage pine3ree-mezzio-pimple-container-test
 * @author     pine3ree https://github.com/pine3ree
 */

namespace pine3ree\test\Mezzio\Pimple\Asset;

use Psr\Container\ContainerInterface;

use pine3ree\test\Mezzio\Pimple\Asset\Service;

class NonInvokableFactory
{
    public function create(ContainerInterface $container): Service
    {
        return new Service($container->get(Dependency::class));
    }
}
