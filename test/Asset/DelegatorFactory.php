<?php

/**
 * @package    pine3ree-mezzio-pimple-container
 * @subpackage pine3ree-mezzio-pimple-container-test
 * @author     pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\test\Mezzio\Pimple\Asset;

use Psr\Container\ContainerInterface;

use pine3ree\test\Mezzio\Pimple\Asset\Service;
use pine3ree\test\Mezzio\Pimple\Asset\Delegator;

class DelegatorFactory
{
    public function __invoke(ContainerInterface $container, string $fqcn, callable $callback, ?array $options = null): Service
    {
//        print_R($fqcn);
//        var_dump(method_exists($callback, '__invoke'));
//        $rm = new \ReflectionMethod($callback, '__invoke');
//        print_r($rm->getParameters());
//        exit;
        return new Delegator($service = $callback());
    }
}
