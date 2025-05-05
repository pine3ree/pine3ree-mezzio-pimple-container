<?php

/**
 * @package    pine3ree-mezzio-pimple-container
 * @subpackage pine3ree-mezzio-pimple-container-test
 * @author     pine3ree https://github.com/pine3ree
 */

use pine3ree\test\Mezzio\Pimple\Asset\Delegator;
use pine3ree\test\Mezzio\Pimple\Asset\DelegatorFactory;
use pine3ree\test\Mezzio\Pimple\Asset\Dependency;
use pine3ree\test\Mezzio\Pimple\Asset\Factory;
use pine3ree\test\Mezzio\Pimple\Asset\Invokable;
use pine3ree\test\Mezzio\Pimple\Asset\Service;

return [
    'dependencies' => [
        'services' => [
            'date' => new DateTimeImmutable(),
        ],
        'invokables' => [
            Invokable::class,
            'dependency' => Dependency::class,
        ],
        'factories' => [
            Service::class => Factory::class,
            Delegator::class => DelegatorFactory::class,
        ],
        'aliases' => [
            'service' => Service::class,
            'invokable' => Invokable::class,
        ],
    ],
];
