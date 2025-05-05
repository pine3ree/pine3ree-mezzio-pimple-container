<?php

/**
 * @package    pine3ree-mezzio-pimple-container
 * @subpackage pine3ree-mezzio-pimple-container-test
 * @author     pine3ree https://github.com/pine3ree
 */

namespace pine3ree\test\Mezzio\Pimple\Asset;

use pine3ree\test\Mezzio\Pimple\Asset\Service;

/**
 * Class Delegator
 */
class Delegator extends Service
{
    private Service $service;

    public function __construct(Service $service)
    {
        $this->service = $service;
    }

    public function getRandomNumber(): int
    {
        return 10 * $this->service->getRandomNumber();
    }
}
