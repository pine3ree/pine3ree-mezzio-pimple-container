<?php

/**
 * @package    pine3ree-mezzio-pimple-container
 * @subpackage pine3ree-mezzio-pimple-container-test
 * @author     pine3ree https://github.com/pine3ree
 */

namespace pine3ree\test\Mezzio\Container\Pimple\Asset;

use pine3ree\test\Mezzio\Container\Pimple\Asset\Dependency;

class Service
{
    private Dependency $dependency;
    private ?int $number = null;

    public function __construct(Dependency $dependency = null)
    {
        $this->dependency = $dependency;
    }

    public function getRandomNumber(): int
    {
        if ($this->dependency instanceof Dependency) {
            $this->number = $this->dependency->getRandomNumber();
            return $this->number;
        }

        return 0;
    }

    public function withNumber(int $number): self
    {
        $this->number = $number;
        return $this;
    }

    public function getNumber(): ?int
    {
        return $this->number;
    }
}
