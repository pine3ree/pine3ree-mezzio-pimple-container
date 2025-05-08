<?php

/**
 * @package    pine3ree-mezzio-pimple-container
 * @subpackage pine3ree-mezzio-pimple-container-test
 * @author     pine3ree https://github.com/pine3ree
 */


declare(strict_types=1);

namespace pine3ree\test\Mezzio\Pimple;

use function array_merge_recursive;

use DateTimeImmutable;
use DirectoryIterator;
use PHPUnit\Framework\TestCase;
use Pimple\Exception\ExpectedInvokableException;
use Psr\Container\ContainerInterface;
use pine3ree\Mezzio\Pimple\ContainerFactory;
use pine3ree\Mezzio\Pimple\Exception\EmptyConfigurationException;
use pine3ree\Mezzio\Pimple\Exception\RuntimeException as ContainerRuntimeException;
use pine3ree\test\Mezzio\Pimple\Asset\ServiceDelegatorFactory;
use pine3ree\test\Mezzio\Pimple\Asset\Dependency;
use pine3ree\test\Mezzio\Pimple\Asset\Extension;
use pine3ree\test\Mezzio\Pimple\Asset\Factory;
use pine3ree\test\Mezzio\Pimple\Asset\Invokable;
use pine3ree\test\Mezzio\Pimple\Asset\InvokableDelegatorFactoryA;
use pine3ree\test\Mezzio\Pimple\Asset\InvokableDelegatorFactoryB;
use pine3ree\test\Mezzio\Pimple\Asset\NonInvokableFactory;
use pine3ree\test\Mezzio\Pimple\Asset\Service;
use pine3ree\test\Mezzio\Pimple\Asset\Simple;

use function time;

class ContainerFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testFactoryRaisesExceptionIfNoDependency()
    {
        $this->expectException(EmptyConfigurationException::class);
        $this->createContainerByConfig(['dependencies' => []]);
    }

    public function testFactoryCreatesPsr11ContainerWithDependencies()
    {
        $config = include __DIR__ . '/config/config.php';
        $container = $this->createContainerByConfig($config);

        self::assertInstanceOf(ContainerInterface::class, $container);
    }

    public function testInjectedObjectServices()
    {
        $config = include __DIR__ . '/config/config.php';

        $container = $this->createContainerByConfig($config);

        self::assertInstanceOf(Simple::class, $container->get('simple'));
        self::assertInstanceOf(DateTimeImmutable::class, $container->get('date'));

        $now = $container->get('date');

        self::assertLessThanOrEqual(time(), $now->getTimeStamp());
        self::assertGreaterThan(time() - 3600, $now->getTimeStamp());
    }

    public function testInjectedNonObjectServices()
    {
        $config = include __DIR__ . '/config/config.php';

        $container = $this->createContainerByConfig($config);

        $settings = $container->get('settings');

        self::assertIsArray($settings);
        self::assertArrayHasKey('keyA', $settings);
        self::assertEquals('valueA', $settings['keyA']);
    }

    public function testInjectedCallableService()
    {
        $config = include __DIR__ . '/config/config.php';

        $container = $this->createContainerByConfig($config);

        $callback = $container->get('callback');

        self::assertIsCallable($callback);
        self::assertSame(42, $callback());
    }

    public function testNoServices()
    {
        $config = include __DIR__ . '/config/config.php';
        // Remove service key from dependencies
        $config['dependencies']['services'] = [];

        $container = $this->createContainerByConfig($config);

        self::assertFalse($container->has('simple'));
        self::assertFalse($container->has('callback'));
    }

    public function testInvokables()
    {
        $config = include __DIR__ . '/config/config.php';

        $container = $this->createContainerByConfig($config);

        self::assertInstanceOf(Dependency::class, $container->get(Dependency::Class));
    }

    public function testNoInvokables()
    {
        $config = include __DIR__ . '/config/config.php';
        $config['dependencies']['invokables'] = [];

        $container = $this->createContainerByConfig($config);

        self::assertFalse($container->has(Invokable::class));
        self::assertFalse($container->has(Dependency::class));
        self::assertFalse($container->has('dependency'));
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

        $container = $this->createContainerByConfig($config);

        $this->expectException(ExpectedInvokableException::class);
        $container->get(NonExistentClass::class);
    }

    public function testFactories()
    {
        $config = include __DIR__ . '/config/config.php';

        $container = $this->createContainerByConfig($config);

        self::assertInstanceOf(Service::class, $container->get(Service::Class));
    }

    public function testNoFactories()
    {
        $config = include __DIR__ . '/config/config.php';
        $config['dependencies']['factories'] = [];

        $container = $this->createContainerByConfig($config);

        self::assertFalse($container->has(Service::class));
        self::assertFalse($container->has(ServiceDelegator::class));
    }

    public function testFactoriesThatAreAlsoRegisteredServices()
    {
        $config = include __DIR__ . '/config/config.php';
        // Explicitly aet Factory as invokable service
        $config['dependencies']['invokables'][Factory::class] = Factory::class;

        $container = $this->createContainerByConfig($config);

        self::assertIsCallable($container->get(Factory::Class));
        self::assertInstanceOf(Service::class, $container->get(Service::Class));
    }

    public function testInvokableFactories()
    {
        $config = include __DIR__ . '/config/config.php';
//        $config['dependencies']['factories']['datetime'] = fn() => new DateTimeImmutable();

        $container = $this->createContainerByConfig($config);

        self::assertInstanceOf(Invokable::class, $inv1 = $container->get(Invokable::class));
        self::assertInstanceOf(Invokable::class, $inv2 = $container->get(Invokable::class));
        self::assertSame($inv1, $inv2);
    }

    public function testThatNonExistentFactoryRaisesException()
    {
        $config = include __DIR__ . '/config/config.php';
        $config['dependencies']['factories'][Service::class] = NonExistentFactory::class;

        $container = $this->createContainerByConfig($config);

        $this->expectException(ExpectedInvokableException::class);
        self::assertInstanceOf(Service::class, $container->get(Service::Class));
    }

    public function testThatInvalidFactoryTypeRaisesException()
    {
        $config = include __DIR__ . '/config/config.php';
        $config['dependencies']['factories'][Service::class] = 123;

        $container = $this->createContainerByConfig($config);

        $this->expectException(ExpectedInvokableException::class);
        self::assertInstanceOf(Service::class, $container->get(Service::Class));
    }

    public function testThatNonInvokableFactoryRaisesException()
    {
        $config = include __DIR__ . '/config/config.php';
        $config['dependencies']['factories'][Service::class] = NonInvokableFactory::class;

        $container = $this->createContainerByConfig($config);

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

        $container = $this->createContainerByConfig($config);
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
                        ServiceDelegatorFactory::class,
                    ],
                ],
            ],
        ]);

        $container = $this->createContainerByConfig($config);
        $service   = $container->get(Service::Class);

        self::assertGreaterThan(Dependency::MAX, $service->getNumber());
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

        $container = $this->createContainerByConfig($config);
        $invokableService = $container->get(Invokable::Class);

        self::assertEquals(InvokableDelegatorFactoryA::class, $invokableService->getProperty('A'));
        self::assertEquals(InvokableDelegatorFactoryB::class, $invokableService->getProperty('B'));
    }

    public function testInvalidDelegatorDefinitionRaisesException()
    {
        $config = include __DIR__ . '/config/config.php';
        $config = array_merge_recursive($config, [
            'dependencies' => [
                'delegators' => [
                    Service::class => [
                        new DateTimeImmutable(), // non-callable
                    ],
                ],
            ],
        ]);

        $container = $this->createContainerByConfig($config);

        $this->expectException(ExpectedInvokableException::class);
        $service = $container->get(Service::Class);
    }

    public function testNonExistentDelegatorClassRaisesException()
    {
        $config = include __DIR__ . '/config/config.php';
        $config = array_merge_recursive($config, [
            'dependencies' => [
                'delegators' => [
                    Service::class => [
                        NonExistentDelegatorClass::class, // non-existent-class
                    ],
                ],
            ],
        ]);

        $container = $this->createContainerByConfig($config);

        $this->expectException(ExpectedInvokableException::class);
        $service = $container->get(Service::Class);
    }

    public function testComplexDelegatorClassWithoutOwnFactoryRaisesException()
    {
        $config = include __DIR__ . '/config/config.php';
        $config = array_merge_recursive($config, [
            'dependencies' => [
                'delegators' => [
                    Service::class => [
                        DirectoryIterator::class, // cannot instantiate without args
                    ],
                ],
            ],
        ]);

        $container = $this->createContainerByConfig($config);

        $this->expectException(ContainerRuntimeException::class);
        $service = $container->get(Service::Class);
    }

    public function testAliases()
    {
        $config = include __DIR__ . '/config/config.php';

        $container = $this->createContainerByConfig($config);

        self::assertInstanceOf(Dependency::class, $container->get('dependency'));
        self::assertInstanceOf(Service::class, $container->get('service'));
    }

    public function testNoAliases()
    {
        $config = include __DIR__ . '/config/config.php';
        $config['dependencies']['aliases'] = [];

        $container = $this->createContainerByConfig($config);

        self::assertFalse($container->has('invokable'));
        self::assertFalse($container->has('service'));
    }

    public function testIsSharedByDefaultIfNotSet()
    {
        $config = include __DIR__ . '/config/config.php';
        $config['dependencies']['shared_by_default'] = 123;

        $container = $this->createContainerByConfig($config);

        // Test injected-services are shared
        self::assertSame($container->get('date'), $container->get('date'));

        // Test invokable-services are shared
        self::assertSame($container->get(Invokable::class), $container->get(Invokable::class));

        // Test factory-services are shared
        self::assertSame($container->get(Dependency::class), $container->get(Dependency::class));

        // Test aliased-services are shared
        self::assertSame($container->get('dependency'), $container->get('dependency'));
    }

    public function testWhenNonSharedByDefault()
    {
        $config = include __DIR__ . '/config/config.php';
        $config['dependencies']['shared_by_default'] = false;

        $container = $this->createContainerByConfig($config);

        // Test injected-services
        self::assertInstanceOf(DateTimeImmutable::class, $date1 = $container->get('date'));

        // Test invokable-services
        self::assertInstanceOf(Invokable::class, $inv1 = $container->get(Invokable::class));

        // Test factory-services
        self::assertInstanceOf(Dependency::class, $dep1 = $container->get(Dependency::class));

        // Test aliased-services
        self::assertInstanceOf(Dependency::class, $ali1 = $container->get('dependency'));
    }

    public function testSharedNegativeOverrides()
    {
        $config = include __DIR__ . '/config/config.php';

        $config['dependencies']['shared_by_default'] = true;
        $config['dependencies']['shared'][Invokable::class] = true;
        $config['dependencies']['shared']['invokable'] = false;

        $container = $this->createContainerByConfig($config);

        // Test invokable-services
        self::assertInstanceOf(Invokable::class, $inv1 = $container->get(Invokable::class));
        self::assertInstanceOf(Invokable::class, $inv2 = $container->get(Invokable::class));
        self::assertSame($inv1, $inv2);

        // Test aliased-services
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

        $container = $this->createContainerByConfig($config);

        self::assertInstanceOf(Invokable::class, $inv1 = $container->get(Invokable::class));
        self::assertInstanceOf(Invokable::class, $inv2 = $container->get(Invokable::class));
        self::assertNotSame($inv1, $inv2);

        self::assertInstanceOf(Invokable::class, $inv1 = $container->get('invokable'));
        self::assertInstanceOf(Invokable::class, $inv2 = $container->get('invokable'));
        self::assertSame($inv1, $inv2);
    }

    public function testNonSharedInjectedService()
    {
        $config = include __DIR__ . '/config/config.php';
        // Override service sharing
        $config['dependencies']['shared']['date'] = false;

        $container = $this->createContainerByConfig($config);

        self::assertInstanceOf(DateTimeImmutable::class, $container->get('date'));
        self::assertNotSame($container->get('date'), $container->get('date'));
    }

    public function testThatInjectedCallablesServiceAreAlwaysShared()
    {
        $config = include __DIR__ . '/config/config.php';
        // Override service sharing
        $config['dependencies']['shared']['callback'] = false;

        $container = $this->createContainerByConfig($config);

        self::assertSame($container->get('callback'), $container->get('callback'));
    }

    public function testConfigurationService()
    {
        $config = include __DIR__ . '/config/config.php';

        $container = $this->createContainerByConfig($config);

        self::assertTrue($container->has('config'));

        self::assertIsArray($container->get('config'));
        self::assertSame($config, $container->get('config'));

        self::assertEquals(42, $config['another'] ?? null);
    }

    private function createContainerByConfig(array $config): ContainerInterface
    {
        $factory = new ContainerFactory();
        return $factory($config['dependencies'], $config);
    }
}
