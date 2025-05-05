<?php

/**
 * @package    pine3ree-mezzio-pimple-container
 * @subpackage pine3ree-mezzio-pimple-container-test
 * @author     pine3ree https://github.com/pine3ree
 */


declare(strict_types=1);

namespace pine3ree\test\Mezzio\Pimple;

use PHPUnit\Framework\TestCase;
use Pimple\Exception\ExpectedInvokableException;
use Psr\Container\ContainerInterface;
use pine3ree\Mezzio\Pimple\ContainerFactory;
use pine3ree\test\Mezzio\Pimple\Asset\DelegatorFactory;
use pine3ree\test\Mezzio\Pimple\Asset\Dependency;
use pine3ree\test\Mezzio\Pimple\Asset\Extension;
use pine3ree\test\Mezzio\Pimple\Asset\Factory;
use pine3ree\test\Mezzio\Pimple\Asset\Invokable;
use pine3ree\test\Mezzio\Pimple\Asset\InvokableDelegatorFactoryA;
use pine3ree\test\Mezzio\Pimple\Asset\InvokableDelegatorFactoryB;
use pine3ree\test\Mezzio\Pimple\Asset\NonInvokableFactory;
use pine3ree\test\Mezzio\Pimple\Asset\Service;


class ContainerFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testFactoryCreatesPsr11Container()
    {
        $container = $this->createContainer([]);

        self::assertInstanceOf(ContainerInterface::class, $container);
    }

    public function testThatInvokables()
    {
        $config = include __DIR__ . '/config/config.php';

        $container = $this->createContainer($config);

        self::assertInstanceOf(Dependency::class, $container->get(Dependency::Class));
    }

    public function testThatInvokablesWithNonExistingClassesRaiseExceptions()
    {
        $config = include __DIR__ . '/config/config.php';
        $config = array_merge_recursive($config, [
            'dependencies' => [
                'invokables' => [
                    NonExistentClass::class,
                ],
            ],
        ]);

        $container = $this->createContainer($config);

        $this->expectException(ExpectedInvokableException::class);
        $container->get(NonExistentClass::class);
    }

    public function testFactories()
    {
        $config = include __DIR__ . '/config/config.php';

        $container = $this->createContainer($config);

        self::assertInstanceOf(Service::class, $container->get(Service::Class));
    }

    public function testFactoriesAlsoServices()
    {
        $config = include __DIR__ . '/config/config.php';
        $config['dependencies']['invokables'][Factory::class] = Factory::class;

        $container = $this->createContainer($config);

        self::assertInstanceOf(Service::class, $container->get(Service::Class));
    }

    public function testThatInvalidFactoryRaisesException()
    {
        $config = include __DIR__ . '/config/config.php';
        $config['dependencies']['factories'][Service::class] = NonExistentFactory::class;

        $container = $this->createContainer($config);

        $this->expectException(ExpectedInvokableException::class);
        self::assertInstanceOf(Service::class, $container->get(Service::Class));
    }

    public function testThatNonInvokableFactoryRaisesException()
    {
        $config = include __DIR__ . '/config/config.php';
        $config['dependencies']['factories'][Service::class] = NonInvokableFactory::class;

        $container = $this->createContainer($config);

         $this->expectException(ExpectedInvokableException::class);
        self::assertInstanceOf(Service::class, $container->get(Service::Class));
    }

    public function testExtensions()
    {
        $config = include __DIR__ . '/config/config.php';
        $config = array_merge_recursive($config, [
            'dependencies' => [
                'extensions' => [
                    Service::class => [
                        Extension::class,
                    ],
                ],
            ],
        ]);

        $container = $this->createContainer($config);
        $service   = $container->get(Service::Class);

        self::assertIsInt($service->getNumber());
    }

    public function testDelegatorsForFactoryService()
    {
        $config = include __DIR__ . '/config/config.php';
        $config = array_merge_recursive($config, [
            'dependencies' => [
                'delegators' => [
                    Service::class => [
                        DelegatorFactory::class,
                    ],
                ],
            ],
        ]);

        $container = $this->createContainer($config);
        $service   = $container->get(Service::Class);

        self::assertGreaterThan(Dependency::MAX, $service->getRandomNumber());
    }

    public function testDelegatorsForInvokableService()
    {
        $config = include __DIR__ . '/config/config.php';
        $config = array_merge_recursive($config, [
            'dependencies' => [
                'delegators' => [
                    Invokable::class => [
                        InvokableDelegatorFactoryA::class,
                        InvokableDelegatorFactoryB::class,
                    ],
                ],
            ],
        ]);

        $container = $this->createContainer($config);
        $invokableService = $container->get(Invokable::Class);

        self::assertEquals(InvokableDelegatorFactoryA::class, $invokableService->getProperty('A'));
        self::assertEquals(InvokableDelegatorFactoryB::class, $invokableService->getProperty('B'));
    }

    public function testAliases()
    {
        $config = include __DIR__ . '/config/config.php';

        $container = $this->createContainer($config);

        self::assertInstanceOf(Dependency::class, $container->get('dependency'));
        self::assertInstanceOf(Service::class, $container->get('service'));
    }

    public function testNonSharedByDefault()
    {
        $config = include __DIR__ . '/config/config.php';
        $config['dependencies']['shared_by_default'] = false;

        $container = $this->createContainer($config);

        self::assertInstanceOf(Dependency::class, $dep1 = $container->get(Dependency::class));
        self::assertInstanceOf(Dependency::class, $dep2 = $container->get(Dependency::class));
        self::assertNotSame($dep1, $dep2);

        self::assertInstanceOf(Dependency::class, $dep1 = $container->get('dependency'));
        self::assertInstanceOf(Dependency::class, $dep2 = $container->get('dependency'));
        self::assertNotSame($dep1, $dep2);
    }

    public function testSharedNegativeOverrides()
    {
        $config = include __DIR__ . '/config/config.php';
        $config['dependencies']['shared_by_default'] = true;
        $config['dependencies']['shared'][Invokable::class] = true;
        $config['dependencies']['shared']['invokable'] = false;

        $container = $this->createContainer($config);

        self::assertInstanceOf(Invokable::class, $inv1 = $container->get(Invokable::class));
        self::assertInstanceOf(Invokable::class, $inv2 = $container->get(Invokable::class));
        self::assertSame($inv1, $inv2);

        self::assertInstanceOf(Invokable::class, $inv1 = $container->get('invokable'));
        self::assertInstanceOf(Invokable::class, $inv2 = $container->get('invokable'));
        self::assertNotSame($inv1, $inv2);
    }

    public function testSharedPositiveOverrides()
    {
        $config = include __DIR__ . '/config/config.php';
        $config['dependencies']['shared_by_default'] = false;
        $config['dependencies']['shared'][Invokable::class] = false;
        $config['dependencies']['shared']['invokable'] = true;

        $container = $this->createContainer($config);

        self::assertInstanceOf(Invokable::class, $inv1 = $container->get(Invokable::class));
        self::assertInstanceOf(Invokable::class, $inv2 = $container->get(Invokable::class));
        self::assertNotSame($inv1, $inv2);

        self::assertInstanceOf(Invokable::class, $inv1 = $container->get('invokable'));
        self::assertInstanceOf(Invokable::class, $inv2 = $container->get('invokable'));
        self::assertSame($inv1, $inv2);
    }

    private function createContainer(array $config)
    {
        $factory = new ContainerFactory($config);
        return $factory();
    }
}
