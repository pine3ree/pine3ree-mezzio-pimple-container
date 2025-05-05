<?php

/**
 * @package    pine3ree-mezzio-pimple-container
 * @subpackage pine3ree-mezzio-pimple-container-test
 * @author     pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\test\Mezzio\Pimple\Asset;

class Invokable
{
    private array $properties = [];

    public function setProperty(string $name, $value): void
    {
        $this->properties[$name] = $value;
    }

    public function getProperty(string $name)
    {
        return $this->properties[$name] ?? null;
    }
}
