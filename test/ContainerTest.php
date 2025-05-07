<?php

/**
 * @package    pine3ree-mezzio-pimple-container
 * @subpackage pine3ree-mezzio-pimple-container-test
 * @author     pine3ree https://github.com/pine3ree
 */


declare(strict_types=1);

namespace pine3ree\test\Mezzio\Pimple;

use PHPUnit\Framework\TestCase;
use pine3ree\Mezzio\Pimple\Container;
use pine3ree\test\Mezzio\Pimple\Asset\Service;
use Psr\Container\NotFoundExceptionInterface;

class ContainerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testGetReturnsExistingService()
    {
        $container = new Container([
            'myService' => fn() => new Service(),
        ]);

        $this->assertSame($container['myService'], $container->get('myService'));
    }

    public function testThatGetThrowsNotFoundExceptionInterfaceIfServiceIsNotFound()
    {
        $this->expectException(NotFoundExceptionInterface::class);

        $container = new Container();
        $container->get('myService');
    }

    public function testHasReturnsTrueIfServiceExists()
    {
        $container = new Container([
            'myService' => fn() => new Service(),
        ]);

        $this->assertTrue($container->has('myService'));
    }

    public function testHasReturnsFalseIfServiceDoesNotExist()
    {
        $container = new Container();

        $this->assertFalse($container->has('myService'));
    }
}
