<?php

/**
 * @package    pine3ree-mezzio-pimple-container
 * @subpackage pine3ree-mezzio-pimple-container-test
 * @author     pine3ree https://github.com/pine3ree
 */

namespace pine3ree\test\Mezzio\Pimple\Asset;

use pine3ree\test\Mezzio\Pimple\Asset\Dependency;

class Service
{
    private ?Dependency $dependency = null;
    private ?int $number = null;

    public function __construct(Dependency $dependency = null)
    {
        $this->dependency = $dependency;
    }

    public function withNumber(int $number): self
    {
        $this->number = $number;
        return $this;
    }

    public function getNumber(): int
    {
        if (isset($this->number)) {
            return $this->number;
        }

        if ($this->dependency instanceof Dependency) {
            return $this->dependency->getRandomNumber();
        }

        return 0;
    }
}
