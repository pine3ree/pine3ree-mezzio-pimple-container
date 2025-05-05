<?php

/**
 * @package    pine3ree-mezzio-pimple-container
 * @subpackage pine3ree-mezzio-pimple-container-test
 * @author     pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\test\Mezzio\Container\Pimple\Asset;

use function mt_rand;

class Dependency
{
    public const MIN = 2;
    public const MAX = 7;

    public function getRandomNumber(): int
    {
        return mt_rand(self::MIN, self::MAX);
    }
}
