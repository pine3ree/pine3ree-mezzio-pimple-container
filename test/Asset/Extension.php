<?php

/**
 * @package    pine3ree-mezzio-pimple-container
 * @subpackage pine3ree-mezzio-pimple-container-test
 * @author     pine3ree https://github.com/pine3ree
 */

namespace pine3ree\test\Mezzio\Container\Pimple\Asset;

use Psr\Container\ContainerInterface;
use pine3ree\test\Mezzio\Container\Pimple\Asset\Service;

use function time;

class Extension
{
    public function __invoke(Service $service, ContainerInterface $container, string $name): Service
    {
        return $service->withNumber(time());
    }
}
