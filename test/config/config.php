<?php

/**
 * @package    pine3ree-mezzio-pimple-container
 * @subpackage pine3ree-mezzio-pimple-container-test
 * @author     pine3ree https://github.com/pine3ree
 */

use pine3ree\test\Mezzio\Pimple\Asset\Dependency;
use pine3ree\test\Mezzio\Pimple\Asset\Factory;
use pine3ree\test\Mezzio\Pimple\Asset\Invokable;
use pine3ree\test\Mezzio\Pimple\Asset\Service;
use pine3ree\test\Mezzio\Pimple\Asset\ServiceDelegator;
use pine3ree\test\Mezzio\Pimple\Asset\ServiceDelegatorFactory;
use pine3ree\test\Mezzio\Pimple\Asset\Simple;

return [
    'dependencies' => [
        'services' => [
            'date' => new DateTimeImmutable(),
            'simple' => new Simple(),
            'settings' => [
                'keyA' => 'valueA',
                'keyB' => 'valueB',
            ],
            'callback' => fn() => 42,
        ],
        'invokables' => [
            Invokable::class,
            'dependency' => Dependency::class,
        ],
        'factories' => [
            Service::class => Factory::class,
            ServiceDelegator::class => ServiceDelegatorFactory::class,
            'datetime' => fn() => new DateTimeImmutable(),
        ],
        'aliases' => [
            'service' => Service::class,
            'invokable' => Invokable::class,
        ],
        'delegators' => [
            // Starts empty, filled in in unit-tests
        ],
        'extensions' => [
            // Starts empty, filled in in unit-tests
        ],
    ],
    // other app configuration keys
    'another' => 42,
];
