<?php

/**
 * @package    pine3ree-mezzio-pimple-container
 * @subpackage pine3ree-mezzio-pimple-container-test
 * @author     pine3ree https://github.com/pine3ree
 */

namespace pine3ree\test\Mezzio\Pimple\Asset;

use pine3ree\test\Mezzio\Pimple\Asset\Service;

use function max;

class ServiceDelegator extends Service
{
    private Service $service;
    private int $multiplier = self::MIN;

    private const MIN = 10;

    public function __construct(Service $service, int $multiplier = self::MIN)
    {
        $this->service = $service;
        $this->multiplier = max(self::MIN, $multiplier);
    }

    public function getNumber(): int
    {
        return $this->multiplier * $this->service->getNumber();
    }
}
